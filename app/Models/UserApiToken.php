<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiToken extends Model
{
    protected $connection = 'naqaa';
    protected $table = 'user_api_tokens';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'Name',
        'Token',
        'Abilities',
        'ExpiresAt',
        'LastUsedAt',
    ];

    protected $hidden = [
        'Token',
    ];

    protected $casts = [
        'Id' => 'integer',
        'UserId' => 'integer',
        'Abilities' => 'array',
        'ExpiresAt' => 'datetime',
        'LastUsedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId');
    }

    public static function findForToken(string $token): ?self
    {
        $hashed = hash('sha256', $token);

        return static::where('Token', $hashed)
            ->where(function ($query) {
                $query->whereNull('ExpiresAt')->orWhere('ExpiresAt', '>=', now());
            })
            ->first();
    }

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['Token'] = hash('sha256', $value);
    }
}

