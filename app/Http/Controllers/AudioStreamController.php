<?php

namespace App\Http\Controllers;

use App\Enums\AudioStatus;
use App\Models\Brief;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a brief's audio from the private disk to an authorized user only. No public URL exists. Symfony's
 * BinaryFileResponse also answers Range requests.
 */
class AudioStreamController extends Controller
{
    public function show(Request $request, Brief $brief): BinaryFileResponse
    {
        Gate::authorize('viewAudio', $brief);

        $path = $brief->audio_path;
        abort_unless($brief->audio_status === AudioStatus::Ready && $path !== null && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
