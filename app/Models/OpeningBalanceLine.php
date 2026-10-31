<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalanceLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function account()
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }
}
