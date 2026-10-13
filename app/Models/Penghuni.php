<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penghuni extends Model
{
    protected $connection = 'mst_vip';

    protected $table = 'penghuni';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $keyType = 'int';

    public $incrementing = true;
}
