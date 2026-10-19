<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $fillable = [
        'menu_id', 'harga_lama', 'harga_baru', 'tanggal_perubahan',
    ];

    protected $casts = [
        'tanggal_perubahan' => 'datetime',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
