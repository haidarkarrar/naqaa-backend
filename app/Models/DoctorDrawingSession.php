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
        'StrokeData' => 'array',
    ];

    protected $fillable = [
        'DoctorId',
        'AdmissionId',
        'StrokeData',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'DoctorId', 'Id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'AdmissionId', 'Id');
    }
}
