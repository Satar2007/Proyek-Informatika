<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
    protected $fillable = [
        'menu_id', 'tipe', 'qty_before',
        'qty_change', 'qty_after', 'catatan', 'created_by'
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}