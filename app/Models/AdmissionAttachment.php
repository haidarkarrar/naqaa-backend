<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAttachment extends Model
{
    protected $connection = 'archive';
    protected $table = 'TblAdmissionAttachments';
    protected $primaryKey = 'Id';

    protected $casts = [
        'UploadedAt' => 'datetime',
    ];

    protected $fillable = [
        'DoctorId',
        'AdmissionId',
        'Path',
        'Mime',
        'Label',
        'UploadedAt',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'AdmissionId', 'Id');
    }
}
