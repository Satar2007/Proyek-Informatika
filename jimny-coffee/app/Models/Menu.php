<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'nama_menu', 'slug', 'deskripsi',
        'harga', 'stok', 'minimum_stok', 'gambar', 'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function getStatusStokAttribute()
    {
        if ($this->stok <= 0) return 'habis';
        if ($this->stok <= $this->minimum_stok) return 'hampir_habis';
        return 'tersedia';
    }
}