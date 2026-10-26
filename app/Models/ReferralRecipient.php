<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralRecipient extends Model
{
    protected $fillable = ['name', 'phone', 'notes', 'is_active'];

    protected function casts(): array { return ['is_active'=>'boolean']; }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/[^\pL\pN]/u', '', mb_strtolower(trim($name), 'UTF-8'));
    }

    protected static function booted(): void
    {
        static::creating(function ($recipient) {
            $recipient->code = 'RR-'.Str::upper((string) Str::uuid());
        });
        static::saving(function ($recipient) {
            $recipient->name = trim(preg_replace('/\s+/u', ' ', $recipient->name));
            $recipient->normalized_name = self::normalizeName($recipient->name);
        });
    }

    public function expenses() { return $this->hasMany(Expense::class); }
}
