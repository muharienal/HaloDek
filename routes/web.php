<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// ===== AUTH (NIK + password) =====
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ===== SEMUA PENGGUNA YANG SUDAH LOGIN =====
// Pantau laporan tanpa login (hanya-baca; tautan pendek bertoken dari notifikasi WhatsApp)
Route::get('/p/{report}/{token}', [\App\Http\Controllers\PublicReportController::class, 'show'])
    ->middleware('throttle:60,1')->where('token', '[a-f0-9]{16}')->name('reports.public');
Route::get('/p/{report}/{token}/{attachment}', [\App\Http\Controllers\PublicReportController::class, 'photo'])
    ->middleware('throttle:120,1')->where('token', '[a-f0-9]{16}')->name('reports.public.photo');

Route::middleware(['auth', 'account', 'throttle:writes'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profil & ganti password
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/whatsapp', [ProfileController::class, 'whatsapp'])->middleware('throttle:30,1')->name('profile.whatsapp');
    Route::put('/profil/password', [ProfileController::class, 'password'])->middleware('throttle:password')->name('profile.password');

    // Notifikasi
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifikasi/baca-semua', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifikasi/{notification}/buka', [NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('/notifikasi/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Lampiran (dicek hak akses per laporan)
    Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Pengumuman
    Route::get('/pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    // QR di barang inventaris -> form lapor terisi otomatis.
    Route::get('/q/{item}', \App\Http\Controllers\ScanController::class)->whereNumber('item')->name('scan');

    Route::get('/cari', [\App\Http\Controllers\SearchController::class, 'index'])->middleware('throttle:60,1')->name('search');

    // ----- Laporan saya (pelapor) -----
    Route::prefix('laporan-saya')->name('employee.reports.')->group(function () {
        Route::get('/', [Employee\ReportController::class, 'index'])->name('index');
        Route::get('/buat', [Employee\ReportController::class, 'create'])->name('create');
        Route::get('/barang', [Employee\ReportController::class, 'items'])->name('items');
        Route::get('/serupa', [Employee\ReportController::class, 'similar'])->middleware('throttle:60,1')->name('similar');
        Route::post('/', [Employee\ReportController::class, 'store'])->middleware('throttle:submit')->name('store');
        Route::get('/{report}', [Employee\ReportController::class, 'show'])->name('show');
        Route::post('/{report}/balas', [Employee\ReportController::class, 'comment'])->middleware('throttle:submit')->name('comment');
        Route::patch('/{report}/batal', [Employee\ReportController::class, 'cancel'])->name('cancel');
        Route::patch('/{report}/beres', [Employee\ReportController::class, 'confirm'])->name('confirm');
        Route::patch('/{report}/buka-lagi', [Employee\ReportController::class, 'reopen'])->middleware('throttle:submit')->name('reopen');
        Route::patch('/{report}/banding', [Employee\ReportController::class, 'appeal'])->middleware('throttle:submit')->name('appeal');
    });

    // ===== AREA PENGELOLA (Superadmin, Manajer, Kepala Regu, Staf Regu) =====
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {

        // Laporan & tindak lanjut
        Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/ekspor', [Admin\ReportController::class, 'export'])->middleware('throttle:bulk')->name('reports.export');
        Route::get('/laporan/{report}', [Admin\ReportController::class, 'show'])->name('reports.show');

        // Laporan bulanan (ringkasan per regu; Excel dan cetak PDF)
        Route::get('/laporan-bulanan', [Admin\MonthlyReportController::class, 'index'])->name('monthly.index');
        Route::get('/laporan-bulanan/cetak', [Admin\MonthlyReportController::class, 'print'])->name('monthly.print');
        Route::get('/laporan-bulanan/unduh', [Admin\MonthlyReportController::class, 'download'])->middleware('throttle:bulk')->name('monthly.download');
        Route::get('/laporan/{report}/cetak', [Admin\ReportController::class, 'print'])->name('reports.print');
        Route::delete('/laporan/{report}', [Admin\ReportController::class, 'destroy'])->name('reports.destroy')->middleware('role:superadmin');

        // Regu & kategori laporan (semua pengelola melihat regunya; kategori dikelola Kepala Regu; Superadmin: kelola penuh)
        Route::get('/regu', [Admin\TeamController::class, 'index'])->name('teams.index');
        Route::get('/regu/{team}', [Admin\TeamController::class, 'show'])->name('teams.show');

        Route::middleware('role:superadmin')->group(function () {
            Route::post('/regu', [Admin\TeamController::class, 'store'])->name('teams.store');
            Route::patch('/regu/{team}', [Admin\TeamController::class, 'update'])->name('teams.update');
            Route::delete('/regu/{team}', [Admin\TeamController::class, 'destroy'])->name('teams.destroy');
            Route::post('/regu/{team}/anggota', [Admin\TeamController::class, 'addMember'])->name('teams.members.add');
            Route::patch('/regu/{team}/anggota/{user}/kepala', [Admin\TeamController::class, 'setHead'])->name('teams.members.head');
            Route::delete('/regu/{team}/anggota/{user}', [Admin\TeamController::class, 'removeMember'])->name('teams.members.remove');
        });

        // ===== Aksi (semua pengelola kecuali Manajer, yang hanya memantau) =====
        Route::middleware('role:superadmin,kepala_regu,staf')->group(function () {
            Route::post('/laporan/massal', [Admin\ReportController::class, 'bulk'])->middleware('throttle:bulk')->name('reports.bulk');
            Route::patch('/laporan/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');
            Route::post('/laporan/{report}/tutup', [Admin\ReportController::class, 'close'])->name('reports.close');
            Route::post('/laporan/{report}/ambil', [Admin\ReportController::class, 'take'])->name('reports.take');
            Route::post('/laporan/{report}/lepas', [Admin\ReportController::class, 'release'])->name('reports.release');
            Route::post('/laporan/{report}/gabung', [Admin\ReportController::class, 'merge'])->name('reports.merge');
            Route::post('/laporan/{report}/pindah', [Admin\ReportController::class, 'transfer'])->name('reports.transfer');
            Route::post('/laporan/{report}/balas', [Admin\ReportController::class, 'comment'])->middleware('throttle:submit')->name('reports.comment');
            Route::post('/regu/{team}/kategori', [Admin\CategoryController::class, 'store'])->name('categories.store');
            Route::patch('/kategori/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/kategori/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::patch('/regu/{team}/template', [Admin\CategoryController::class, 'templates'])->name('teams.templates');

            Route::post('/pengumuman', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::patch('/pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
            Route::delete('/pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });

        // Superadmin: pengguna & audit
        Route::middleware('role:superadmin')->group(function () {
            Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('/pengguna/template', [Admin\UserController::class, 'template'])->name('users.template');
            Route::get('/pengguna/ekspor', [Admin\UserController::class, 'export'])->middleware('throttle:bulk')->name('users.export');
            Route::post('/pengguna/impor', [Admin\UserController::class, 'import'])->middleware('throttle:bulk')->name('users.import');
            Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store');
            Route::patch('/pengguna/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('/pengguna/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::patch('/pengguna/{user}/status', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            Route::delete('/pengguna/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

            Route::get('/organisasi', [Admin\OrganizationController::class, 'index'])->name('org.index');
            Route::put('/organisasi/peran/{role}', [Admin\OrganizationController::class, 'updateRole'])->name('org.role');
            Route::post('/organisasi/{type}', [Admin\OrganizationController::class, 'store'])->whereIn('type', ['divisi', 'bidang', 'jabatan'])->name('org.store');
            Route::put('/organisasi/{type}/{id}', [Admin\OrganizationController::class, 'update'])->whereIn('type', ['divisi', 'bidang', 'jabatan'])->whereNumber('id')->name('org.update');
            Route::delete('/organisasi/{type}/{id}', [Admin\OrganizationController::class, 'destroy'])->whereIn('type', ['divisi', 'bidang', 'jabatan'])->whereNumber('id')->name('org.destroy');

            Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/status', [Admin\SystemStatusController::class, 'index'])->name('status.index');
            Route::get('/whatsapp', [Admin\WhatsAppSettingsController::class, 'edit'])->name('wa.edit');
            Route::put('/whatsapp', [Admin\WhatsAppSettingsController::class, 'update'])->name('wa.update');
            Route::post('/status/uji-wa', [Admin\SystemStatusController::class, 'testWhatsApp'])->middleware('throttle:6,1')->name('status.wa-test');
        });
    });
});
