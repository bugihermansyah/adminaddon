<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    protected $connection = 'trs_vip';

    protected $table = 'trs';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $keyType = 'int';

    public $incrementing = true;

    public function posIn(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'PosInID', 'ID');
    }

    public function posOut(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'PosOutID', 'ID');
    }
}
