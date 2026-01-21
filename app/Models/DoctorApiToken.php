<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorApiToken extends Model
{
    protected $connection = 'meditop';
    protected $table = 'doctor_api_tokens';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'DoctorId',
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
        'Abilities' => 'array',
        'ExpiresAt' => 'datetime',
        'LastUsedAt' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
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
