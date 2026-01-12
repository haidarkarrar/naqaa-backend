<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalAdmissionForm extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblDigitalAdmissionForms';
    protected $primaryKey = 'Id';

    protected $casts = [
        'payload' => 'array',
        'strokes' => 'array',
    ];

    protected $fillable = [
        'doctor_id',
        'admission_id',
        'payload',
        'strokes',
        'form_version',
        'status',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'admission_id', 'Id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'Id');
    }
}
