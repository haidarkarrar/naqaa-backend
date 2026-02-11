<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalAdmissionForm extends Model
{
    protected $connection = 'naqaa';
    protected $table = 'TblDigitalAdmissionForms';
    protected $primaryKey = 'Id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'DoctorId',
        'AdmissionId',
        'Payload',
        'Strokes',
        'FormVersion',
        'Status',
    ];

    protected $casts = [
        'Payload' => 'array',
        'Strokes' => 'array',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'AdmissionId', 'Id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
    }
}
