<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'created_by');
    }

    public function delete()
    {
        if (!$this->exists) {
            return null;
        }

        return DB::transaction(function () {
            /*
            |--------------------------------------------------------------------------
            | Lock akun
            |--------------------------------------------------------------------------
            |
            | TransactionService juga mengunci row user sebelum checkout.
            | Dengan begitu checkout baru dan penghapusan akun tidak bisa
            | berjalan saling mendahului tanpa melihat kondisi terbaru.
            |
            */

            $lockedUser = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedUser) {
                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | Tolak penghapusan jika masih ada proses QRIS aktif
            |--------------------------------------------------------------------------
            */

            $hasReservedStock = Transaction::query()
                ->where('user_id', $lockedUser->id)
                ->where('stock_reservation_status', 'reserved')
                ->exists();

            if ($hasReservedStock) {
                throw ValidationException::withMessages([
                    'account' => (
                        'Akun tidak dapat dihapus karena masih memiliki '
                        .'reservasi stok QRIS yang aktif.'
                    ),
                ]);
            }

            $hasPendingQris = Transaction::query()
                ->where('user_id', $lockedUser->id)
                ->where('payment_method', 'qris')
                ->where('status', 'pending')
                ->where('payment_status', 'unpaid')
                ->exists();

            if ($hasPendingQris) {
                throw ValidationException::withMessages([
                    'account' => (
                        'Akun tidak dapat dihapus karena masih memiliki '
                        .'transaksi QRIS yang menunggu penyelesaian.'
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Pastikan snapshot historis tersedia
            |--------------------------------------------------------------------------
            */

            Transaction::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('cashier_name_snapshot')
                ->update([
                    'cashier_name_snapshot' => $lockedUser->name,
                ]);

            Transaction::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('cashier_role_snapshot')
                ->update([
                    'cashier_role_snapshot' => $lockedUser->role,
                ]);

            StockLog::query()
                ->where('created_by', $lockedUser->id)
                ->whereNull('creator_name_snapshot')
                ->update([
                    'creator_name_snapshot' => $lockedUser->name,
                ]);

            StockLog::query()
                ->where('created_by', $lockedUser->id)
                ->whereNull('creator_role_snapshot')
                ->update([
                    'creator_role_snapshot' => $lockedUser->role,
                ]);

            /*
            |--------------------------------------------------------------------------
            | Delete
            |--------------------------------------------------------------------------
            |
            | Migration financial-history mengubah FK transaksi dan stock log
            | menjadi ON DELETE SET NULL, sehingga history tetap tersimpan.
            |
            */

            return parent::delete();
        });
    }
}
