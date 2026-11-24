<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Support\Collection;

class CoaReportGroups
{
    public const UNMAPPED_INCOME = 'REV_UNMAPPED';

    public static function incomeCode(?string $assigned, ?string $guarantorCategory, bool $isBpjs): string
    {
        return $assigned ?? match (true) {
            $isBpjs || $guarantorCategory === 'BPJS' => 'REV_BPJS',
            in_array($guarantorCategory, ['PRIVATE_INSURANCE', 'CORPORATE', 'OTHER'], true) => 'REV_GUARANTOR',
            default => 'REV_SERVICE',
        };
    }

    public static function receiptCode(Receipt $receipt): string
    {
        $group = $receipt->account?->financialGroup;
        return self::incomeCode(
            $group?->type === 'INCOME' && $group->is_active ? $group->code : null,
            $receipt->guarantor?->category,
            (bool) $receipt->guarantor?->is_bpjs,
        );
    }

    public static function totals(Collection $rows, string $type, callable $codeForRow, Collection $groups): Collection
    {
        $names = $groups->where('type', $type)->pluck('name', 'code');
        $unmapped = $type === 'INCOME' ? self::UNMAPPED_INCOME : 'EXP_UNMAPPED';
        $names[$unmapped] = $type === 'INCOME' ? 'Pemasukan tanpa grup laporan' : 'Pengeluaran tanpa grup laporan';

        return $rows->groupBy(function ($row) use ($codeForRow, $names, $unmapped) {
            $code = $codeForRow($row);
            return $code !== null && $names->has($code) ? $code : $unmapped;
        })->map(fn ($entries, $code) => [
            'code' => $code,
            'name' => $names[$code],
            'total' => $entries->sum(fn ($row) => LedgerAmounts::cents($row->total)) / 100,
            'transactions' => $entries->sum('transactions'),
        ])->sortByDesc('total')->values();
    }
}
