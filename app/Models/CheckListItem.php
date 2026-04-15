<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckListItem extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblCheckListItems';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $casts = [
        'Id' => 'integer',
        'CheckListId' => 'integer',
    ];

    public function checkList(): BelongsTo
    {
        return $this->belongsTo(CheckList::class, 'CheckListId', 'Id');
    }

    public function patientCheckedItems(): HasMany
    {
        return $this->hasMany(PatientCheckedItem::class, 'ItemId', 'Id');
    }
}
