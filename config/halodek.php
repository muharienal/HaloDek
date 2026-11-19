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
    'tagline' => 'Layanan Pelaporan Terpadu',
    'company' => env('COMPANY_NAME', 'PT Petrokopindo Cipta Selaras'),

    // Role pengguna.
    //  - superadmin  : akses penuh ke semua regu, pengguna, dan audit.
    //  - kepala_regu : memimpin satu regu; menugaskan staf atau menangani sendiri.
    //  - staf        : anggota satu regu; mengerjakan tugas yang diberikan atau mengambil laporan yang belum ditugaskan.
    //  - karyawan    : pelapor (masuk cukup dengan NIK).
    'roles' => [
        'superadmin' => 'Superadmin',
        'manajer' => 'Manajer',
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

    // 'hint' tampil di form lapor agar pelapor memilih urgensi dengan tepat.
    'priorities' => [
        'rendah' => ['label' => 'Rendah', 'tone' => 'gray', 'hint' => 'Bisa menunggu, tidak mengganggu pekerjaan'],
        'sedang' => ['label' => 'Sedang', 'tone' => 'teal', 'hint' => 'Mengganggu, tapi masih bisa bekerja'],
        'tinggi' => ['label' => 'Tinggi', 'tone' => 'orange', 'hint' => 'Pekerjaan terhambat, perlu segera'],
        'mendesak' => ['label' => 'Mendesak', 'tone' => 'red', 'hint' => 'Pekerjaan terhenti atau ada bahaya'],
    ],

    // Jam kerja: dasar hitungan durasi respons/penyelesaian dan batas respons (bukan jam kalender).
    // days: 1 = Senin ... 7 = Minggu. Hari libur: isi HALODEK_LIBUR di .env, mis. 2026-12-25,2027-01-01
    'work_hours' => [
        'days' => [1, 2, 3, 4, 5],
        'start' => '07:00',
        'end' => '16:00',
        'holidays' => array_values(array_filter(array_map('trim', explode(',', (string) env('HALODEK_LIBUR', ''))))),
    ],

    // Batas respons (JAM KERJA) sebelum laporan yang belum diambil dianggap "lewat batas".
    // Lewat batas: tampil merah di dasbor/daftar, dan Kepala Regu diingatkan lewat WhatsApp + notifikasi.
    'response_sla' => [
        'mendesak' => 1,
        'tinggi' => 2,
        'sedang' => 4,
        'rendah' => 8,
    ],

    // Pengingat otomatis dari dalam aplikasi (tanpa Task Scheduler): dicek saat ada yang membuka HaloDek,
    // paling sering sekali per sekian menit.
    'auto_reminder' => (bool) env('HALODEK_PENGINGAT_OTOMATIS', true),
    'reminder_every_minutes' => 15,

    // Pembersihan otomatis sekali sehari (juga tanpa Task Scheduler). Satuan: hari.
    'auto_maintenance' => (bool) env('HALODEK_PEMBERSIHAN_OTOMATIS', true),
    'retention' => [
        'notifications_read_days' => 90,    // notifikasi yang sudah dibaca
        'notifications_unread_days' => 180, // notifikasi yang tidak pernah dibaca
        'audit_days' => 365,                // log audit
    ],

    // Template catatan cepat bawaan (popup Selesaikan / Tolak). Tiap regu bisa mengubahnya di menu Regu.
    'note_templates' => [
        'selesai' => [
            'Sudah diperbaiki, silakan dicek kembali.',
            'Sudah diganti dengan yang baru.',
            'Sudah ditangani di lokasi. Hubungi kami bila terulang.',
        ],
        'ditolak' => [
            'Permintaan ini perlu persetujuan atasan terlebih dahulu.',
            'Di luar wewenang regu kami, silakan ajukan ke regu yang sesuai.',
            'Laporan ganda, sudah ditangani di laporan lain.',
        ],
    ],

    // Pelapor boleh membuka kembali laporan selesai paling lama sekian hari setelah diselesaikan.
    'reopen_days' => 7,

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
