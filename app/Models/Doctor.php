<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $connection = 'meditop';
    protected $table = 'TblDoctors';
    protected $primaryKey = 'Id';
    
    protected $fillable = [
        'FirstName',
        'MiddleName',
        'LastName',
        'FullName',
        'Username',
        'Email',
        'SpecialtyId',
        'Radiologist',
        'Approved',
        'Password',
    ];

    protected $hidden = [
        'Password',
    ];

    protected $casts = [
        'Radiologist' => 'boolean',
        'Approved' => 'boolean',
    ];

    public function admissions(): HasMany
    {
        return $this->hasMany(AdmissionFile::class, 'DoctorId', 'Id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(DoctorApiToken::class, 'DoctorId');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(DoctorRefreshToken::class, 'DoctorId');
    }
}
