<?php

namespace App\Services\Meetings;

/**
 * Checks that an excerpt really occurs in the transcript and returns the transcript's own text at the match
 * (original case and spacing). Deterministic: no clock, no randomness.
 */
final class ExcerptVerifier
{
    /** @var array{0: string, 1: string, 2: list<int>}|null  [transcript, normalized, map] cache of the last transcript */
    private ?array $cache = null;

    public function __construct(private readonly TextNormalizer $text) {}

    public function verify(string $transcriptNfc, string $rawExcerpt): ?string
    {
        $needle = $this->text->normalize($rawExcerpt);
        $length = mb_strlen($needle);

        if ($length < 10 || $length > 500) {
            return null;
        }

        if ($this->cache === null || $this->cache[0] !== $transcriptNfc) {
            [$hay, $map] = $this->text->normalizeWithMap($transcriptNfc);
            $this->cache = [$transcriptNfc, $hay, $map];
        }

        [, $hay, $map] = $this->cache;
        $position = mb_strpos($hay, $needle);

        if ($position === false) {
            return null;
        }

        $start = $map[$position];
        $end = $map[$position + $length - 1];

        return mb_substr($transcriptNfc, $start, $end - $start + 1);
    }
}
