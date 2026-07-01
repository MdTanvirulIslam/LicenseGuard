<?php

namespace Vendor\LicenseGuard\Support;

class EnvFileWriter
{
    /**
     * Replace (or append) KEY=value lines in an .env file, leaving every
     * other line -- comments, blank lines, unrelated variables -- untouched.
     *
     * @param  array<string, string>  $values
     * @return array{added: string[], updated: string[]}
     */
    public function write(string $path, array $values): array
    {
        $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];

        $added = [];
        $updated = [];
        $remaining = $values;

        foreach ($lines as $index => $line) {
            foreach ($remaining as $key => $value) {
                if ($line === $key || str_starts_with($line, $key.'=')) {
                    $lines[$index] = $key.'='.$this->quoteIfNeeded($value);
                    $updated[] = $key;
                    unset($remaining[$key]);
                    continue 2;
                }
            }
        }

        foreach ($remaining as $key => $value) {
            $lines[] = $key.'='.$this->quoteIfNeeded($value);
            $added[] = $key;
        }

        file_put_contents($path, implode("\n", $lines)."\n");

        return ['added' => $added, 'updated' => $updated];
    }

    private function quoteIfNeeded(string $value): string
    {
        if ($value === '' || str_contains($value, ' ') || str_contains($value, '#')) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
