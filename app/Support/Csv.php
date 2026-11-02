<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Utilitas CSV tanpa dependensi: ekspor (UTF-8 BOM supaya rapi di Excel)
 * dan pembacaan impor dengan deteksi pemisah "," atau ";" (Excel Indonesia).
 */
class Csv
{
    /** Respons unduhan CSV dari iterable baris. */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV/formula injection ketika dibuka di Excel. */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Baca file CSV unggahan. Mengembalikan [header, rows] di mana tiap row
     * adalah array asosiatif yang sudah di-trim, beserta nomor barisnya.
     *
     * @return array{0: array<int,string>, 1: array<int, array{line:int, data:array<string,string>}>}
     */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [[], []];
        }

        $first = fgets($handle);
        $first = $first === false ? '' : preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv($first, $delimiter));
        $rows = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = array_pad($row, count($header), '');
            $rows[] = [
                'line' => $line,
                'data' => array_map(fn ($v) => trim((string) $v), array_combine($header, array_slice($row, 0, count($header)))),
            ];
        }

        fclose($handle);

        return [$header, $rows];
    }
}
