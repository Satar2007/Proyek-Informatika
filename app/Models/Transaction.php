<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (!$transaction->user_id) {
                return;
            }

            if (
                filled($transaction->cashier_name_snapshot)
                && filled($transaction->cashier_role_snapshot)
            ) {
                return;
            }

            $user = User::query()->find($transaction->user_id);

            if (!$user) {
                return;
            }

            if (blank($transaction->cashier_name_snapshot)) {
                $transaction->cashier_name_snapshot = $user->name;
            }

            if (blank($transaction->cashier_role_snapshot)) {
                $transaction->cashier_role_snapshot = $user->role;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function getCashierNameAttribute(): string
    {
        return (string) (
            $this->cashier_name_snapshot
            ?: $this->user?->name
            ?: 'Akun dihapus'
        );
    }

    public function getCashierRoleAttribute(): string
    {
        return (string) (
            $this->cashier_role_snapshot
            ?: $this->user?->role
            ?: 'user'
        );
    }

    public function getCashierDisplayNameAttribute(): string
    {
        return $this->cashier_name;
    }

    public function getCashierDisplayRoleAttribute(): string
    {
        return $this->cashier_role;
    }
}
