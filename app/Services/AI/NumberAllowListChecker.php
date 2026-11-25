<?php

namespace App\Services\AI;

use App\Services\Intelligence\MetricCalculator;

/**
 * Rejects AI wording that contains a number which is not part of the item's own evidence. Numbers are compared by
 * value (thousands separators and sign ignored, 2 and 4 decimal forms accepted), and date parts of the evidence dates
 * are allowed so wording may mention the period.
 */
final class NumberAllowListChecker
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function passes(string $wording, array $evidence): bool
    {
        $allowed = $this->allowed($evidence);

        foreach ($this->tokens($wording) as $token) {
            if (! $this->isAllowed($token, $allowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $matches);

        return array_map(fn (string $token) => $this->normalize($token), $matches[0]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function allowed(array $evidence): array
    {
        $allowed = [];

        array_walk_recursive($evidence, function ($value) use (&$allowed): void {
            if (is_int($value)) {
                $allowed[] = (string) abs($value);

                return;
            }

            if (! is_string($value)) {
                return;
            }

            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $date) === 1) {
                array_push($allowed, $date[1], (string) (int) $date[2], (string) (int) $date[3]);
            }

            if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
                $clean = ltrim($value, '-');
                $allowed[] = $this->normalize($clean);
                $allowed[] = $this->metrics->roundHalfUp($clean, 2);
            }
        });

        // Counts of listed items (for example missing branch-days) may be stated.
        $allowed[] = (string) count($evidence['missingDueBranchDays'] ?? []);

        return array_values(array_unique($allowed));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function isAllowed(string $token, array $allowed): bool
    {
        foreach ($allowed as $value) {
            if (bccomp($token, $value, 4) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $token): string
    {
        $token = rtrim(str_replace(',', '', $token), '.');

        return $token === '' ? '0' : $token;
    }
}
