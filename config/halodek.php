<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi khusus aplikasi HaloDek
|--------------------------------------------------------------------------
| Semua konstanta domain (role, status, prioritas, dll.) dikumpulkan di sini
| supaya tidak tersebar di banyak file.
*/

return [

    'name' => 'HaloDek',
    'tagline' => 'Layanan Pengaduan & Pelaporan Terpadu',
    'company' => env('COMPANY_NAME', 'PT Petrokopindo Cipta Selaras'),

    // Role pengguna.
    //  - superadmin  : akses penuh ke semua regu, pengguna, dan audit.
    //  - kepala_regu : memimpin satu regu; menugaskan staf atau menangani sendiri.
    //  - staf        : anggota satu regu; mengerjakan tugas yang diberikan atau mengambil laporan yang belum ditugaskan.
    //  - karyawan    : pelapor (masuk cukup dengan NIK).
    'roles' => [
        'superadmin' => 'Superadmin',
        'kepala_regu' => 'Kepala Regu',
        'staf' => 'Staf Regu',
        'karyawan' => 'Karyawan',
    ],

    // Regu bawaan (sistem). Tidak bisa dihapus / dimatikan; regu lain boleh ditambah Superadmin.
    'system_teams' => ['it', 'sarpras', 'kesekretariatan', 'keamanan'],

    'statuses' => [
        'menunggu' => ['label' => 'Menunggu', 'tone' => 'amber'],
        'diproses' => ['label' => 'Diproses', 'tone' => 'blue'],
        'selesai' => ['label' => 'Selesai', 'tone' => 'green'],
        'ditolak' => ['label' => 'Ditolak', 'tone' => 'red'],
        'dibatalkan' => ['label' => 'Dibatalkan', 'tone' => 'gray'],
    ],

    'priorities' => [
        'rendah' => ['label' => 'Rendah', 'tone' => 'gray'],
        'sedang' => ['label' => 'Sedang', 'tone' => 'teal'],
        'tinggi' => ['label' => 'Tinggi', 'tone' => 'orange'],
        'mendesak' => ['label' => 'Mendesak', 'tone' => 'red'],
    ],

    // Status item inventaris.
    'item_statuses' => [
        'digunakan' => ['label' => 'Digunakan', 'tone' => 'blue'],
        'tersedia' => ['label' => 'Tersedia', 'tone' => 'green'],
        'rusak' => ['label' => 'Rusak', 'tone' => 'red'],
        'perbaikan' => ['label' => 'Perbaikan', 'tone' => 'amber'],
        'non-aktif' => ['label' => 'Non-aktif', 'tone' => 'gray'],
    ],

    // Palet warna regu (nama => hex). Dipakai untuk badge, kartu, dan grafik.
    'team_colors' => [
        'blue' => '#1D5FD6',
        'green' => '#15803D',
        'teal' => '#0F766E',
        'gold' => '#A16207',
        'indigo' => '#4338CA',
        'emerald' => '#047857',
        'amber' => '#B45309',
        'violet' => '#7C3AED',
        'rose' => '#E11D48',
        'cyan' => '#0E7490',
        'orange' => '#C2410C',
        'slate' => '#475569',
    ],

    // Ikon yang boleh dipilih untuk regu (harus ada di components/icon.blade.php).
    'team_icons' => ['server', 'building', 'shield', 'users', 'wrench', 'truck', 'bolt', 'heart', 'document', 'sliders'],

    'attachments' => [
        'max_files' => 5,
        'max_kb' => 3072,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'], // hanya foto
    ],

    'per_page' => 12,
];
