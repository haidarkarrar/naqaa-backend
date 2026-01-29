<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TblDocument extends Model
{
    protected $connection = 'archive';
    protected $table = 'TblDocuments';
    protected $primaryKey = 'Id';

    public $timestamps = false;
}
