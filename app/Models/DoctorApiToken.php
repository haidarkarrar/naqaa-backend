<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorApiToken extends Model
{
    protected $connection = 'meditop';
    protected $table = 'doctor_api_tokens';

    protected $fillable = [
        'doctor_id',
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'Id');
    }

    public static function findForToken(string $token): ?self
    {
        $hashed = hash('sha256', $token);

        return static::where('token', $hashed)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->first();
    }

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = hash('sha256', $value);
    }
}
