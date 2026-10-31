<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\OpeningBalance;
use App\Models\User;

class SystemAssistantAccess
{
    public function role(User $user, array $roles): bool
    {
        return $user->is_active !== false && in_array($user->accountingRole(), $roles, true);
    }

    public function resources(User $user): array
    {
        if ($user->is_active === false) {
            return [];
        }
        $out = [];
        if ($this->role($user, ['KASIR', 'ASURANSI', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'ATASAN', 'FINANCE', 'OWNER', 'DIREKTUR'])) {
            $out['receipts'] = 'Penerimaan / pemasukan';
        }
        if ($this->role($user, ['KASIR', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'ATASAN', 'FINANCE', 'OWNER', 'DIREKTUR'])) {
            $out['expenses'] = 'Pengeluaran umum';
        }
        if ($user->can('viewAny', Asset::class)) {
            $out['assets'] = 'Inventaris barang';
        }
        if ($this->role($user, ['ASURANSI', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN', 'OWNER', 'DIREKTUR'])) {
            $out['claims'] = 'Tracking klaim asuransi';
        }
        if ($this->role($user, ['KASIR', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KEPALA_KEUANGAN']) || $user->can('viewAny', OpeningBalance::class)) {
            $out['accounts'] = 'Akun / COA';
        }

        return $out;
    }

    public function analyses(User $user): array
    {
        $out = [];
        if ($user->can('viewAny', OpeningBalance::class)) {
            $out = ['transactions' => 'Laporan penerimaan & pengeluaran', 'balance' => 'Neraca / perubahan saldo'];
        }
        if ($user->can('viewAny', Asset::class)) {
            $out['assets'] = 'Grafik inventaris terkini';
        }
        if (isset($this->resources($user)['claims'])) {
            $out['claims'] = 'Tracking klaim terkini';
        }

        return $out;
    }

    public function receiptRoute(User $user): string
    {
        return match ($user->accountingRole()) {
            'KASIR' => 'cashier.receipts.index', 'ASURANSI' => 'insurance.receipts.index',
            'ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN' => 'accounting.receipts.index',
            default => 'reports.income',
        };
    }

    public function expenseRoute(User $user): string
    {
        return match ($user->accountingRole()) {
            'KASIR' => 'cashier.expenses.index',
            'ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN' => 'accounting.pengeluaran.index',
            default => 'reports.expense',
        };
    }
}
