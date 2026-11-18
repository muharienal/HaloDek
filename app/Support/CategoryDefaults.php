<?php

namespace App\Support;

/**
 * Kata kunci pencarian + petunjuk deskripsi bawaan untuk kategori regu sistem.
 * Dipakai seeder (instalasi baru) dan migrasi (mengisi data lama yang masih kosong).
 * Setelah itu Kepala Regu bebas mengubahnya di menu Regu.
 */
class CategoryDefaults
{
    /** nama kategori => [kata kunci, petunjuk deskripsi] */
    public const MAP = [
        // IT
        'Komputer & Laptop' => ['pc, komputer, laptop, lemot, lambat, hang, mati, blue screen, keyboard, mouse, monitor, layar', 'Sebutkan merek/kode perangkat dan gejalanya. Sudah dicoba restart?'],
        'Printer & Scanner' => ['print, printer, cetak, tinta, toner, macet, kertas nyangkut, scan, scanner', 'Printer di ruang mana dan pesan error apa yang muncul?'],
        'Internet & WiFi' => ['wifi, wi-fi, internet, jaringan, lan, kabel, koneksi, lambat, putus, sinyal', 'Di lokasi mana? Semua perangkat terdampak atau hanya satu?'],
        'Aplikasi / Sistem Informasi' => ['aplikasi, sistem, erp, sap, error, website, web, software, program', 'Nama aplikasi, langkah yang dilakukan, dan pesan error. Lampirkan tangkapan layar bila ada.'],
        'Email Korporat' => ['email, e-mail, outlook, surel, kirim, terima, spam, kotak masuk', 'Alamat email terkait dan pesan error yang muncul.'],
        'Akses & Akun' => ['password, kata sandi, akun, login, masuk, lupa, terkunci, akses, reset', 'Akun atau aplikasi apa yang tidak bisa diakses?'],
        'Permintaan Perangkat / Software' => ['permintaan, pengadaan, minta, install, instal, lisensi, perangkat baru, software baru', 'Perangkat/software yang dibutuhkan dan untuk keperluan apa.'],
        // Sarana dan Prasarana
        'Gedung & Bangunan' => ['atap, bocor, plafon, dinding, tembok, pintu, jendela, lantai, kunci, cat, retak', 'Bagian gedung yang rusak dan sejak kapan.'],
        'Kendaraan Operasional' => ['mobil, motor, kendaraan, ban, bensin, bbm, servis, mogok', 'Nomor polisi kendaraan dan kendalanya.'],
        'Kelistrikan' => ['listrik, lampu, stop kontak, colokan, mcb, sekring, padam, mati lampu, korslet', 'Lokasi dan gejalanya. Jika ada percikan atau bau hangus, jauhi area tersebut.'],
        'Meja Kursi & Furniture' => ['meja, kursi, lemari, rak, laci, furniture, mebel', 'Barang apa, di ruang mana, dan kerusakannya.'],
        'Toilet & Sanitasi' => ['toilet, wc, kamar mandi, kloset, wastafel, mampet, bau, tisu', 'Toilet di lantai/gedung mana dan masalahnya.'],
        'Air & Plumbing' => ['air, pipa, kran, keran, bocor, mampet, pompa, saluran', 'Lokasi kebocoran atau gangguan air.'],
        'Kebersihan' => ['kotor, sampah, bersih, sapu, pel, hama, tikus, kecoa, semut', 'Area mana yang perlu dibersihkan.'],
        'AC' => ['ac, pendingin, panas, gerah, bocor, netes, menetes, berisik, freon', 'AC di ruang mana dan gejalanya (tidak dingin, menetes, berisik).'],
        // Kesekretariatan
        'Surat Menyurat' => ['surat, nomor surat, disposisi, tanda tangan, kop', 'Jenis surat dan tenggat waktunya.'],
        'Arsip & Dokumen' => ['arsip, dokumen, berkas, salinan, fotokopi, legalisir', 'Dokumen apa yang dibutuhkan dan untuk keperluan apa.'],
        'Perlengkapan Kantor (ATK)' => ['atk, kertas, pulpen, pena, map, stapler, amplop, alat tulis', 'Barang dan jumlah yang dibutuhkan.'],
        'Ruang Rapat & Acara' => ['rapat, meeting, ruang rapat, booking, pinjam ruang, acara, konsumsi, proyektor', 'Tanggal, jam, jumlah peserta, dan kebutuhan ruangan.'],
        'Perjalanan Dinas' => ['dinas, perjalanan, tiket pesawat, hotel, spj, akomodasi', 'Tujuan, tanggal berangkat/pulang, dan jumlah orang.'],
        'Ekspedisi & Kurir' => ['kirim, paket, kurir, ekspedisi, pengiriman', 'Barang yang dikirim, alamat tujuan, dan tenggat.'],
        // Keamanan
        'Keamanan Area' => ['orang asing, mencurigakan, pencurian, maling, patroli, satpam', 'Lokasi dan waktu kejadian. Untuk keadaan darurat, hubungi pos jaga langsung.'],
        'Kehilangan Barang' => ['hilang, kehilangan, dompet, kunci, hp, barang tertinggal', 'Barang apa, ciri-cirinya, dan terakhir terlihat di mana.'],
        'Akses Gedung & Parkir' => ['parkir, kartu akses, id card, gerbang, portal, palang', 'Akses atau area mana yang bermasalah.'],
        'CCTV' => ['cctv, kamera, rekaman', 'Kamera di lokasi mana, atau rekaman tanggal/jam berapa yang dibutuhkan.'],
        'Kecelakaan / Near Miss' => ['kecelakaan, jatuh, luka, terpeleset, licin, near miss, hampir celaka, k3', 'Kronologi singkat, lokasi, dan ada korban atau tidak.'],
        'APAR & Evakuasi' => ['apar, pemadam, kebakaran, api, evakuasi, alarm, hydrant, jalur evakuasi', 'Lokasi dan kondisi APAR/alat yang bermasalah.'],
    ];

    /** @return array{keywords:?string, hint:?string} */
    public static function for(string $name): array
    {
        [$kw, $hint] = self::MAP[$name] ?? [null, null];

        return ['keywords' => $kw, 'hint' => $hint];
    }
}
