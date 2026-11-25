<?php

namespace App\Services\Imports;

use Generator;
use Illuminate\Http\UploadedFile;
use SplFileObject;

/**
 * File-level validation and streaming parse of an import CSV. Reads no database and stores nothing.
 * The column specification and limits come from config/imports.php.
 */
final class CsvImportReader
{
    private const BOM = "\xEF\xBB\xBF";

    /** @return list<string> the template columns in order */
    public function headerColumns(): array
    {
        return array_keys(config('imports.columns'));
    }

    public function templateHeader(): string
    {
        return implode(',', $this->headerColumns());
    }

    /**
     * Run the file-level checks in order; the first failure wins.
     *
     * @throws ImportRejectedException
     */
    public function validate(UploadedFile $file): void
    {
        $path = (string) $file->getRealPath();

        if (! str_ends_with(strtolower($file->getClientOriginalName()), '.csv')) {
            throw $this->reject('invalid_extension', 'The file must have the .csv extension.');
        }

        // The server-detected type is used, never the type the client claims.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, config('imports.allowed_mime_types'), true)) {
            throw $this->reject('invalid_mime', 'The file is not a CSV text file.');
        }

        $size = filesize($path);
        if ($size > config('imports.max_file_bytes')) {
            throw $this->reject('file_too_large', 'The file is larger than the 5 MB limit.');
        }

        if ($size === 0) {
            throw $this->reject('empty_file', 'The file is empty. Use the template to prepare a file with a header row.');
        }

        $content = (string) file_get_contents($path);
        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw $this->reject('invalid_encoding', 'The file must be saved as UTF-8.');
        }

        if (trim($content) === '') {
            throw $this->reject('empty_file', 'The file has no header row.');
        }
        unset($content);

        $header = $this->readHeader($path);
        $this->validateHeader($header);

        // Count data rows in the same streaming pass used for parsing, stopping early past the limit.
        $count = 0;
        foreach ($this->rows($path) as $ignored) {
            if (++$count > config('imports.max_rows')) {
                throw $this->reject('too_many_rows', 'The file has more than '.number_format(config('imports.max_rows')).' data rows.');
            }
        }
    }

    /**
     * Stream the data rows of a validated file. Yields [rowNumber (data rows counted from 2), cells keyed by
     * header name]. Blank lines are ignored; header names are case-insensitive and trimmed; column order is free.
     * A row with a different number of cells than the header yields cells = null (wrong_column_count); the
     * third element always carries the raw cells by position.
     *
     * @return Generator<int, array{0: int, 1: array<string, string>|null, 2: list<string>}>
     */
    public function rows(string $path): Generator
    {
        $handle = $this->open($path);
        $header = null;
        $rowNumber = 1;

        foreach ($handle as $record) {
            if (! is_array($record) || $record === [null] || $record === [] || $this->isBlank($record)) {
                continue;
            }

            if ($header === null) {
                $record[0] = $this->stripBom((string) $record[0]);
                $header = array_map(fn ($name) => strtolower(trim((string) $name)), $record);

                continue;
            }

            $rowNumber++;
            $cells = array_map(fn ($cell) => (string) $cell, $record);

            yield $rowNumber => [
                $rowNumber,
                count($cells) === count($header) ? array_combine($header, $cells) : null,
                array_values($cells),
            ];
        }
    }

    /**
     * The normalized header names (lower case, trimmed) of the first non-blank line.
     *
     * @return list<string>
     */
    public function readHeader(string $path): array
    {
        foreach ($this->open($path) as $record) {
            if (is_array($record) && $record !== [null] && ! $this->isBlank($record)) {
                $record[0] = $this->stripBom((string) $record[0]);

                return array_map(fn ($name) => strtolower(trim((string) $name)), $record);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $header
     */
    private function validateHeader(array $header): void
    {
        if ($header === []) {
            throw $this->reject('empty_file', 'The file has no header row.');
        }

        $allowed = $this->headerColumns();
        $required = array_keys(array_filter(config('imports.columns'), fn (array $column) => $column['required']));

        $missing = array_values(array_diff($required, $header));
        if ($missing !== []) {
            throw $this->reject('missing_columns', 'The header is missing: '.implode(', ', $missing).'.', $missing);
        }

        $duplicates = array_values(array_unique(array_diff_assoc($header, array_unique($header))));
        if ($duplicates !== []) {
            throw $this->reject('duplicate_header', 'The header repeats: '.implode(', ', $duplicates).'.', $duplicates);
        }

        $unexpected = array_values(array_diff($header, $allowed));
        if ($unexpected !== []) {
            throw $this->reject('unexpected_columns', 'The header has unexpected columns: '.implode(', ', $unexpected).'.', $unexpected);
        }
    }

    private function reject(string $reason, string $message, array $details = []): ImportRejectedException
    {
        return new ImportRejectedException($reason, $message.' Expected header: '.$this->templateHeader(), $details);
    }

    /**
     * Open the file for CSV streaming. A file with only carriage-return line endings is normalized first.
     */
    private function open(string $path): SplFileObject
    {
        $head = (string) file_get_contents($path, false, null, 0, 65536);

        if (! str_contains($head, "\n") && str_contains($head, "\r")) {
            $stream = new SplFileObject('php://temp', 'w+');
            $stream->fwrite(str_replace("\r", "\n", (string) file_get_contents($path)));
            $stream->rewind();
        } else {
            $stream = new SplFileObject($path, 'r');
        }

        $stream->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $stream->setCsvControl(',', '"', '');

        return $stream;
    }

    /**
     * @param  array<int, mixed>  $record
     */
    private function isBlank(array $record): bool
    {
        return count($record) === 1 && trim((string) $record[0]) === '';
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, self::BOM) ? substr($value, 3) : $value;
    }
}
