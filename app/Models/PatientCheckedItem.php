<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCheckedItem extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblPatientCheckedItems';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'PatientId',
        'ItemId',
        'Date',
        'Note',
    ];

    protected $casts = [
        'Id' => 'integer',
        'PatientId' => 'integer',
        'ItemId' => 'integer',
        'Date' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'PatientId', 'Id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CheckListItem::class, 'ItemId', 'Id');
    }
}
