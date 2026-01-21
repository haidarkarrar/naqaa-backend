<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionFile extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblAdmFiles';
    protected $primaryKey = 'Id';

    protected $casts = [
        'AdmDate' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'PatientId', 'Id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
    }

    public function digitalForm(): HasOne
    {
        return $this->hasOne(
            DigitalAdmissionForm::class,
            'AdmissionId',
            'Id'
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AdmissionAttachment::class, 'AdmissionId', 'Id');
    }

}
