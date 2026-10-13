<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pos extends Model
{
    protected $connection = 'mst_vip';

    protected $table = 'pos';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $keyType = 'int';

    public $incrementing = false;
}
