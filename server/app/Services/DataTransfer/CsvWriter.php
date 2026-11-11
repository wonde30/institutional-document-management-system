<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * Writes a CSV file with the protections a spreadsheet application requires.
 *
 * Two properties matter for an institutional repository:
 *
 *  - **Formula injection.** A cell whose content begins with a character a
 *    spreadsheet interprets as a formula is prefixed with an apostrophe, so the
 *    content is always read as text.
 *  - **Unicode.** The file is written as UTF-8 with a byte order mark, so a
 *    spreadsheet application opens Amharic text correctly.
 */
class CsvWriter
{
    /** @var resource */
    protected $handle;

    protected bool $headerWritten = false;

    /**
     * @param  resource  $handle
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Open a writer for the given path.
     */
    public static function open(string $path): self
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open '{$path}' for writing.");
        }

        // UTF-8 byte order mark, so a spreadsheet application detects the
        // encoding instead of mangling non Latin characters.
        fwrite($handle, "\xEF\xBB\xBF");

        return new self($handle);
    }

    /**
     * Write the header row.
     *
     * @param  list<string>  $columns
     */
    public function writeHeader(array $columns): void
    {
        fputcsv($this->handle, array_map(fn (string $column) => $this->neutralise($column), $columns), ',', '"', '');
        $this->headerWritten = true;
    }

    /**
     * Write one record.
     *
     * @param  array<string, mixed>  $record
     */
    public function writeRow(array $record): void
    {
        fputcsv($this->handle, array_map(fn ($value) => $this->neutralise($this->stringify($value)), array_values($record)), ',', '"', '');
    }

    /**
     * The number of bytes written so far.
     */
    public function size(): int
    {
        clearstatcache(true, $this->streamPath());

        $stat = fstat($this->handle);

        return (int) ($stat['size'] ?? 0);
    }

    /**
     * Close the file.
     */
    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * The path of the open stream.
     */
    protected function streamPath(): string
    {
        $meta = stream_get_meta_data($this->handle);

        return (string) ($meta['uri'] ?? '');
    }

    /**
     * Convert a value to its string representation.
     */
    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * Neutralise a value that a spreadsheet application would interpret as a
     * formula.
     */
    protected function neutralise(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
