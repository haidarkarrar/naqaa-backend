<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionStatusAudit extends Model
{
    protected $connection = 'naqaa';
    protected $table = 'admission_status_audits';

    protected $fillable = [
        'admission_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'id' => 'integer',
        'admission_id' => 'integer',
        'changed_by_user_id' => 'integer',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

