<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleAudit extends Model
{
    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'actor_email',
      'actor_role',
        'target_user_id',
        'target_name',
        'target_email',
        'event',
        'old_role',
        'new_role',
        'ip_address',
        'user_agent',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
