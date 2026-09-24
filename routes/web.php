<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\AkunController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\SmartAssistantController;

// Root diarahkan ke halaman login
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth Routes
require __DIR__ . '/auth.php';

// =====================
// SEMUA ROLE: KASIR, ADMIN, OWNER
// =====================
Route::middleware(['auth', 'role:kasir,admin,owner'])->group(function () {

    // Smart Assistant: bantuan kontekstual untuk semua role tanpa menambah menu baru
    Route::get('/smart-assistant/insights', [SmartAssistantController::class, 'insights'])->name('smart-assistant.insights');
    Route::post('/smart-assistant/chat', [SmartAssistantController::class, 'chat'])->name('smart-assistant.chat');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Laporan
    Route::get('/laporan/harian', [LaporanController::class, 'harian'])->name('laporan.harian');
    Route::get('/laporan/bulanan', [LaporanController::class, 'bulanan'])->name('laporan.bulanan');

    // Transaksi lihat saja
    Route::get('/transaksi', [TransactionController::class, 'index'])->name('transaksi.index');
    Route::get('/transaksi/{id}', [TransactionController::class, 'show'])->name('transaksi.show');
});

// =====================
// KASIR & ADMIN
// =====================
Route::middleware(['auth', 'role:kasir,admin'])->group(function () {

    // Kasir POS
    Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');
    Route::post('/kasir/checkout', [KasirController::class, 'checkout'])->name('kasir.checkout');

    // Payment
    Route::get('/payment/struk/{id}', [PaymentController::class, 'struk'])->name('payment.struk');
    Route::get('/payment/check/{id}', [PaymentController::class, 'check'])->name('payment.check');
    Route::get('/payment/{id}', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/payment/success/{id}', [PaymentController::class, 'success'])->name('payment.success');

    // Transaksi Action
    Route::post('/transaksi/{id}/cancel', [TransactionController::class, 'cancel'])->name('transaksi.cancel');
    Route::post('/transaksi/{id}/expire', [TransactionController::class, 'expire'])->name('transaksi.expire');
    Route::delete('/transaksi/{id}', [TransactionController::class, 'destroy'])->name('transaksi.destroy');

    // Absensi - Clock In/Out
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::get('/attendance/status', [AttendanceController::class, 'status'])->name('attendance.status');

    // Izin
    Route::post('/izin', [LeaveRequestController::class, 'store'])->name('izin.store');
});

// =====================
// ADMIN ONLY
// =====================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard Admin
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/transaksi', [AdminController::class, 'transaksi'])->name('transaksi');
    Route::get('/stok-log', [AdminController::class, 'stokLog'])->name('stok-log');

    // Menu & Stok
    // Route riwayat harga WAJIB didaftarkan sebelum Route::resource di bawah,
    // supaya "/menu/price-history" tidak ketabrak oleh route show resource
    // ("/menu/{menu}") yang mengira "price-history" adalah id menu.
    Route::get('/menu/price-history', [MenuController::class, 'priceHistory'])
        ->name('menu.price-history');

    Route::post('/menu/{menu}/tambah-stok', [MenuController::class, 'tambahStok'])
        ->name('menu.tambah-stok');
    Route::post('/menu/{menu}/kurangi-stok', [MenuController::class, 'kurangiStok'])
        ->name('menu.kurangi-stok');

    Route::resource('/menu', MenuController::class)->names('menu');

    // Shift & Absensi
    Route::get('/shift/rekap', [ShiftController::class, 'rekap'])->name('shift.rekap');
    Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::post('/shift', [ShiftController::class, 'store'])->name('shift.store');
    Route::delete('/shift/{shift}', [ShiftController::class, 'destroy'])->name('shift.destroy');

    // Izin
    Route::get('/izin', [LeaveRequestController::class, 'index'])->name('izin.index');
    Route::post('/izin/{id}/approve', [LeaveRequestController::class, 'approve'])->name('izin.approve');
    Route::post('/izin/{id}/reject', [LeaveRequestController::class, 'reject'])->name('izin.reject');

    // Akun
    Route::get('/akun', [AkunController::class, 'index'])->name('akun.index');
    Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
    Route::put('/akun/{user}', [AkunController::class, 'update'])->name('akun.update');
    Route::delete('/akun/{user}', [AkunController::class, 'destroy'])->name('akun.destroy');
});

// =====================
// OWNER ONLY
// =====================
Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {

    // Dashboard Owner
    Route::get('/dashboard', [OwnerController::class, 'dashboard'])->name('dashboard');

    // Rekap Kehadiran Read-Only
    Route::get('/rekap-kehadiran', [OwnerController::class, 'rekapKehadiran'])->name('rekap-kehadiran');
});