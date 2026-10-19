<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
    protected $fillable = [
        'menu_id',
        'tipe',
        'qty_before',
        'qty_change',
        'qty_after',
        'catatan',
        'created_by',
        'creator_name_snapshot',
        'creator_role_snapshot',

        // Alias kompatibilitas untuk service reservasi stok.
        'created_by_name',
        'created_by_role',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockLog $log) {
            if (!$log->created_by) {
                return;
            }

            if (
                filled($log->creator_name_snapshot)
                && filled($log->creator_role_snapshot)
            ) {
                return;
            }

            $user = User::query()->find($log->created_by);

            if (!$user) {
                return;
            }

            if (blank($log->creator_name_snapshot)) {
                $log->creator_name_snapshot = $user->name;
            }

            if (blank($log->creator_role_snapshot)) {
                $log->creator_role_snapshot = $user->role;
            }
        });
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setCreatedByNameAttribute(?string $value): void
    {
        if (
            filled($value)
            && blank($this->creator_name_snapshot)
        ) {
            $this->attributes['creator_name_snapshot'] = $value;
        }
    }

    public function setCreatedByRoleAttribute(?string $value): void
    {
        if (
            filled($value)
            && blank($this->creator_role_snapshot)
        ) {
            $this->attributes['creator_role_snapshot'] = $value;
        }
    }

    public function getCreatorDisplayNameAttribute(): string
    {
        return (string) (
            $this->creator_name_snapshot
            ?: $this->createdBy?->name
            ?: 'Akun dihapus'
        );
    }

    public function getCreatorDisplayRoleAttribute(): string
    {
        return (string) (
            $this->creator_role_snapshot
            ?: $this->createdBy?->role
            ?: 'user'
        );
    }
}
