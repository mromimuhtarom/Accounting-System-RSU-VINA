<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LedgerAmounts
{
    public static function cents(mixed $value): int
    {
        $text = (string) ($value ?? '0');
        if (! preg_match('/^(-?)(\d{1,16})(?:\.(\d{1,2}))?$/D', $text, $m)) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus berupa desimal, maksimal 2 angka di belakang koma.']);
        }
        $result = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return ($m[1] ?? '') === '-' ? -$result : $result;
    }

    public static function decimal(int $cents): string
    {
        $abs = abs($cents);

        return ($cents < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        $abs = abs($cents);

        return ($cents < 0 ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) intdiv($abs, 100)).','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
