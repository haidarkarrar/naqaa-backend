<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRefreshToken extends Model
{
    protected $connection = 'naqaa';
    protected $table = 'user_refresh_tokens';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'UserId',
        'DeviceId',
        'TokenHash',
        'ExpiresAt',
        'RevokedAt',
        'UserAgent',
        'IpAddress',
        'LastUsedAt',
        'CreatedAt',
        'UpdatedAt',
    ];

    protected $casts = [
        'Id' => 'integer',
        'UserId' => 'integer',
        'ExpiresAt' => 'datetime',
        'RevokedAt' => 'datetime',
        'LastUsedAt' => 'datetime',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId');
    }

    public static function findForTokenAndDevice(string $token, string $deviceId): ?self
    {
        $hashed = hash('sha256', $token);

        return static::where('TokenHash', $hashed)
            ->where('DeviceId', $deviceId)
            ->whereNull('RevokedAt')
            ->where(function ($query) {
                $query->whereNull('ExpiresAt')->orWhere('ExpiresAt', '>=', now());
            })
            ->first();
    }

    public function setTokenHashAttribute(string $value): void
    {
        $this->attributes['TokenHash'] = hash('sha256', $value);
    }
}

