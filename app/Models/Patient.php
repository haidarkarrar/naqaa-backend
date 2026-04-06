<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblPatients';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    
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
        'Allergies',
        'Diabetic',
        'Pregnancy',
        'CardiacFailure',
        'RenalFailure',
        'OtherDisease',
    ];

    protected $casts = [
        'Id' => 'integer',
        'GenderId' => 'integer',
        'MainDoctorId' => 'integer',
        'GuarantorId' => 'integer',
        'Smoker' => 'boolean',
        'Alcoholic' => 'boolean',
        'Allergies' => 'boolean',
        'Diabetic' => 'boolean',
        'Pregnancy' => 'boolean',
        'CardiacFailure' => 'boolean',
        'RenalFailure' => 'boolean',
        'OtherDisease' => 'boolean',
    ];

    public function admissions(): HasMany
    {
        return $this->hasMany(AdmissionFile::class, 'PatientId', 'Id');
    }
}
