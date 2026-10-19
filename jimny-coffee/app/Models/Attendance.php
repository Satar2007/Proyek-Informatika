<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'tanggal',
        'clock_in',
        'clock_out',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}