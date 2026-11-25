<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\Meetings\RecordingStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Recording stream and download through authorized routes only. There is no public URL. BinaryFileResponse answers
 * Range requests (206) so the player can seek.
 */
class MeetingMediaController extends Controller
{
    public function stream(Meeting $meeting): BinaryFileResponse
    {
        Gate::authorize('stream', $meeting);

        return $this->file($meeting, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    public function download(Meeting $meeting): BinaryFileResponse
    {
        Gate::authorize('download', $meeting);

        return $this->file($meeting, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function file(Meeting $meeting, string $disposition): BinaryFileResponse
    {
        $path = $meeting->recording_path;
        abort_unless($path !== null && $meeting->recording_deleted_at === null && Storage::disk('local')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = pathinfo((string) $meeting->recording_original_name, PATHINFO_FILENAME) ?: 'recording';
        $contentType = in_array($meeting->recording_mime, array_merge(...array_values(RecordingStorage::MIME_BY_EXTENSION)), true)
            ? $meeting->recording_mime : 'application/octet-stream';

        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition($disposition, $name.'.'.$extension, 'recording.'.$extension);

        return $response;
    }
}
