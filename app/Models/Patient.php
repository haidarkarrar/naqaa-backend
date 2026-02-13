<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblPatients';
    protected $primaryKey = 'Id';
    
    protected $fillable = [
        'First',
        'Middle',
        'Last',
        'Mother',
        'GenderId',
        'Weight',
        'DOB',
        'POB',
        'IDNum',
        'NationalityId',
        'BloodGroupId',
        'ArabicName',
        'Phone',
        'Email',
        'City',
        'Street',
        'HomeTel',
        'Address',
        'JobTel',
        'GuarantorId',
        'MaritalStatusId',
        'OFD',
        'MainDoctorId',
        'Smoker',
        'Alcoholic',
        'MedicalHistory',
        'SurgicalHistory',
    ];

    protected $casts = [
        'Id' => 'integer',
        'GenderId' => 'integer',
        'MainDoctorId' => 'integer',
        'GuarantorId' => 'integer',
    ];

    public function admissions(): HasMany
    {
        return $this->hasMany(AdmissionFile::class, 'PatientId', 'Id');
    }
}
