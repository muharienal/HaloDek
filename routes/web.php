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

    // ----- Laporan saya (pelapor) -----
    Route::prefix('laporan-saya')->name('employee.reports.')->group(function () {
        Route::get('/', [Employee\ReportController::class, 'index'])->name('index');
        Route::get('/buat', [Employee\ReportController::class, 'create'])->name('create');
        Route::get('/barang', [Employee\ReportController::class, 'items'])->name('items');
        Route::post('/', [Employee\ReportController::class, 'store'])->middleware('throttle:submit')->name('store');
        Route::get('/{report}', [Employee\ReportController::class, 'show'])->name('show');
        Route::post('/{report}/balas', [Employee\ReportController::class, 'comment'])->middleware('throttle:submit')->name('comment');
        Route::patch('/{report}/batal', [Employee\ReportController::class, 'cancel'])->name('cancel');
    });

    // ===== AREA PENGELOLA (Superadmin, Kepala Regu, Staf Regu) =====
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {

        // Laporan & tindak lanjut
        Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/ekspor', [Admin\ReportController::class, 'export'])->middleware('throttle:bulk')->name('reports.export');
        Route::get('/laporan/{report}', [Admin\ReportController::class, 'show'])->name('reports.show');
        Route::get('/laporan/{report}/cetak', [Admin\ReportController::class, 'print'])->name('reports.print');
        Route::patch('/laporan/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');
        Route::post('/laporan/{report}/ambil', [Admin\ReportController::class, 'take'])->name('reports.take');
        Route::post('/laporan/{report}/lepas', [Admin\ReportController::class, 'release'])->name('reports.release');
        Route::post('/laporan/{report}/pindah', [Admin\ReportController::class, 'transfer'])->name('reports.transfer');
        Route::post('/laporan/{report}/balas', [Admin\ReportController::class, 'comment'])->middleware('throttle:submit')->name('reports.comment');
        Route::delete('/laporan/{report}', [Admin\ReportController::class, 'destroy'])->name('reports.destroy')->middleware('role:superadmin');

        // Regu & kategori laporan (semua pengelola melihat regunya; kategori dikelola Kepala Regu; Superadmin: kelola penuh)
        Route::get('/regu', [Admin\TeamController::class, 'index'])->name('teams.index');
        Route::get('/regu/{team}', [Admin\TeamController::class, 'show'])->name('teams.show');
        Route::post('/regu/{team}/kategori', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/kategori/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategori/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::middleware('role:superadmin')->group(function () {
            Route::post('/regu', [Admin\TeamController::class, 'store'])->name('teams.store');
            Route::patch('/regu/{team}', [Admin\TeamController::class, 'update'])->name('teams.update');
            Route::delete('/regu/{team}', [Admin\TeamController::class, 'destroy'])->name('teams.destroy');
            Route::post('/regu/{team}/anggota', [Admin\TeamController::class, 'addMember'])->name('teams.members.add');
            Route::patch('/regu/{team}/anggota/{user}/kepala', [Admin\TeamController::class, 'setHead'])->name('teams.members.head');
            Route::delete('/regu/{team}/anggota/{user}', [Admin\TeamController::class, 'removeMember'])->name('teams.members.remove');
        });

        // Inventaris per regu: kategori + item (hak akses dijaga di controller)
        Route::prefix('inventaris')->name('inventory.')->group(function () {
            Route::get('/', [Admin\InventoryController::class, 'index'])->name('index');
            Route::get('/template', [Admin\InventoryController::class, 'template'])->name('template');
            Route::get('/ekspor', [Admin\InventoryController::class, 'export'])->middleware('throttle:bulk')->name('export');
            Route::post('/impor', [Admin\InventoryController::class, 'import'])->middleware('throttle:bulk')->name('import');

            Route::post('/kategori', [Admin\InventoryController::class, 'storeCategory'])->name('categories.store');
            Route::patch('/kategori/{inventoryCategory}', [Admin\InventoryController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/kategori/{inventoryCategory}', [Admin\InventoryController::class, 'destroyCategory'])->name('categories.destroy');

            Route::post('/item', [Admin\InventoryController::class, 'store'])->name('items.store');
            Route::get('/item/{item}', [Admin\InventoryController::class, 'show'])->name('items.show');
            Route::patch('/item/{item}', [Admin\InventoryController::class, 'update'])->name('items.update');
            Route::delete('/item/{item}', [Admin\InventoryController::class, 'destroy'])->name('items.destroy');
        });

        // Lokasi/ruangan umum untuk form pelaporan: Superadmin & Kepala Regu
        Route::middleware('role:superadmin,kepala_regu')->group(function () {
            Route::get('/lokasi', [Admin\LocationController::class, 'index'])->name('locations.index');
            Route::get('/lokasi/template', [Admin\LocationController::class, 'template'])->name('locations.template');
            Route::post('/lokasi/impor', [Admin\LocationController::class, 'import'])->middleware('throttle:bulk')->name('locations.import');
            Route::post('/lokasi', [Admin\LocationController::class, 'store'])->name('locations.store');
            Route::patch('/lokasi/{location}', [Admin\LocationController::class, 'update'])->name('locations.update');
            Route::delete('/lokasi/{location}', [Admin\LocationController::class, 'destroy'])->name('locations.destroy');
        });

        // Pengumuman (admin dapat membuat; ruang lingkup dijaga di controller)
        Route::post('/pengumuman', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('/pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

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

            Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});
