<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medical_record_no',
        'name',
        'gender',
        'birth_date',
        'phone',
        'address',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function referralExpenses()
    {
        return $this->hasMany(Expense::class)->where('expense_type', 'REFERRAL');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
