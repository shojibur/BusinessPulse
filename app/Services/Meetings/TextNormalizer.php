<?php

namespace App\Services\Meetings;

use Normalizer;

/**
 * Normalized_Text: Unicode NFC, lowercase, each run of whitespace collapsed to one space, trimmed. Pure.
 */
final class TextNormalizer
{
    public function nfc(string $text): string
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        return $normalized === false ? $text : $normalized;
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower($this->nfc($text));
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Normalized characters of an NFC text and, for each, the character index in that text it came from.
     *
     * @return array{0: string, 1: list<int>}
     */
    public function normalizeWithMap(string $nfcText): array
    {
        $out = [];
        $map = [];
        $pendingSpace = false;
        $started = false;

        foreach (mb_str_split($nfcText) as $i => $char) {
            if (preg_match('/^[\s\x{00A0}]$/u', $char) === 1) {
                $pendingSpace = $started;

                continue;
            }

            if ($pendingSpace) {
                $out[] = ' ';
                $map[] = $i - 1;
                $pendingSpace = false;
            }

            foreach (mb_str_split(mb_strtolower($char)) as $lower) {
                $out[] = $lower;
                $map[] = $i;
            }

            $started = true;
        }

        return [implode('', $out), $map];
    }
}
