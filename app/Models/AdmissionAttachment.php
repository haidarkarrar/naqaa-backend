<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAttachment extends Model
{
    protected $connection = 'archive';
    protected $table = 'TblAdmissionAttachments';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    protected $fillable = [
        'doctor_id',
        'admission_id',
        'path',
        'mime',
        'label',
        'uploaded_at',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionFile::class, 'admission_id', 'Id');
    }
}
