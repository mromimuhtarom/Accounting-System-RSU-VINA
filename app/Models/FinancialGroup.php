<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function guarantors()
    {
        return $this->hasMany(Guarantor::class);
    }

    public function accounts()
    {
        return $this->hasMany(ChartAccount::class);
    }
}
