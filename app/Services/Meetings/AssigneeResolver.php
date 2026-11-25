<?php

namespace App\Services\Meetings;

use App\Enums\AssigneeResolution;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Maps a spoken name to ONE user of the Allowed_Staff_List, deterministically. The provider's assignee id is not an
 * input, so a fabricated or injected id can never matter. Ambiguity (two people share the name) is never guessed.
 */
final class AssigneeResolver
{
    public function __construct(private readonly TextNormalizer $text) {}

    /**
     * @param  Collection<int, User>  $staff  active staff and managers of the meeting's department
     */
    public function resolve(?string $name, Collection $staff): AssigneeResult
    {
        if ($name === null || trim($name) === '') {
            return new AssigneeResult(AssigneeResolution::None, null);
        }

        $needle = $this->text->normalize($name);
        $rows = $staff->map(fn ($user) => ['id' => (int) $user->id, 'name' => $this->text->normalize((string) $user->name)])->values();

        $full = $rows->where('name', $needle)->pluck('id');

        if ($full->count() === 1) {
            return new AssigneeResult(AssigneeResolution::Resolved, $full->first());
        }

        if ($full->count() >= 2) {
            return new AssigneeResult(AssigneeResolution::Ambiguous, null);
        }

        if (! str_contains($needle, ' ')) {
            $first = $rows->filter(fn (array $row) => (explode(' ', $row['name'])[0] ?? '') === $needle)->pluck('id');

            if ($first->count() === 1) {
                return new AssigneeResult(AssigneeResolution::Resolved, $first->first());
            }

            if ($first->count() >= 2) {
                return new AssigneeResult(AssigneeResolution::Ambiguous, null);
            }
        }

        return new AssigneeResult(AssigneeResolution::Unmatched, null);
    }
}
