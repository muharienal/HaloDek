<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aksi atas laporan tidak bisa dijalankan karena keadaannya sudah berubah
 * (mis. sudah diambil orang lain sedetik sebelumnya, atau sudah dibatalkan pelapor).
 * Ditampilkan sebagai pesan biasa, bukan halaman error.
 */
class ReportConflict extends RuntimeException
{
}
