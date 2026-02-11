<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorRefreshToken extends Model
{
    protected $connection = 'naqaa';
    protected $table = 'doctor_refresh_tokens';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'DoctorId',
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
        'ExpiresAt' => 'datetime',
        'RevokedAt' => 'datetime',
        'LastUsedAt' => 'datetime',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
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
