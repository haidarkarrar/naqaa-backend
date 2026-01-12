<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorDrawingSession extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblDoctorDrawingSessions';
    protected $primaryKey = 'Id';

    protected $casts = [
        'stroke_data' => 'array',
    ];

    protected $fillable = [
        'doctor_id',
        'admission_id',
        'stroke_data',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'Id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'admission_id', 'Id');
    }
}
