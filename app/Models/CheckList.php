<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckList extends Model
{
    protected $connection = 'meditop';
    protected $table = 'TblCheckLists';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $casts = [
        'Id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CheckListItem::class, 'CheckListId', 'Id');
    }
}
