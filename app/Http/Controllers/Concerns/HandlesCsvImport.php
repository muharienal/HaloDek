<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Alur impor CSV yang seragam untuk semua modul:
 * baris yang gagal dilewati dan dilaporkan, baris lain tetap diproses.
 */
trait HandlesCsvImport
{
    private const IMPORT_MAX_ROWS = 2000;

    /**
     * @param  array<int,string>  $required  kolom wajib pada header
     * @param  callable(array<string,string>, int): void  $handler  melempar ValidationException bila baris tidak valid
     * @return array{ok:int, errors:array<int,string>, fatal:?string}
     */
    protected function runImport(Request $request, array $required, callable $handler): array
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pilih file CSV terlebih dahulu.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        [$header, $rows] = Csv::read($request->file('file')->getRealPath());

        $missing = array_diff($required, $header);
        if ($missing) {
            return ['ok' => 0, 'errors' => [], 'fatal' => 'Format CSV tidak sesuai. Kolom wajib: '.implode(', ', $required).'. Kolom yang tidak ditemukan: '.implode(', ', $missing).'.'];
        }

        if (count($rows) > self::IMPORT_MAX_ROWS) {
            return ['ok' => 0, 'errors' => [], 'fatal' => 'Maksimal '.self::IMPORT_MAX_ROWS.' baris per impor.'];
        }

        $ok = 0;
        $errors = [];

        foreach ($rows as $row) {
            try {
                $handler($row['data'], $row['line']);
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = "Baris {$row['line']}: ".$e->validator->errors()->first();
            }
        }

        return ['ok' => $ok, 'errors' => $errors, 'fatal' => null];
    }

    /** Terima Y-m-d, d/m/Y, atau d-m-Y (format Excel Indonesia). */
    protected function parseDate(?string $value, string $field = 'tanggal'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        throw ValidationException::withMessages([$field => "Format {$field} \"{$value}\" tidak dikenali (gunakan YYYY-MM-DD)."]);
    }

    /** Pesan hasil impor yang seragam. */
    protected function importResponse(array $result, string $routeName, string $noun)
    {
        if ($result['fatal']) {
            return redirect()->route($routeName)->with('error', $result['fatal']);
        }

        $message = "{$result['ok']} {$noun} berhasil diimpor.";
        if ($result['errors']) {
            $message .= ' '.count($result['errors']).' baris dilewati.';
        }

        return redirect()->route($routeName)->with('success', $message)->with('importErrors', $result['errors']);
    }
}
