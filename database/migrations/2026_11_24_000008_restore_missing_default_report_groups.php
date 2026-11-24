<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'REV_SERVICE' => ['Pendapatan Pelayanan', 'INCOME', 10],
            'REV_BPJS' => ['Pendapatan BPJS', 'INCOME', 20],
            'REV_GUARANTOR' => ['Asuransi & Penjamin', 'INCOME', 30],
            'EXP_HR' => ['Beban SDM', 'EXPENSE', 10],
            'EXP_MEDICAL' => ['Beban Medis', 'EXPENSE', 20],
            'EXP_OPERATIONAL' => ['Beban Operasional', 'EXPENSE', 30],
        ];
        $existing = DB::table('financial_groups')->whereIn('code', array_keys($defaults))->pluck('code')
            ->map(fn ($code) => strtoupper($code))->all();
        $missing = array_diff_key($defaults, array_flip($existing));
        if ($missing === []) {
            return;
        }

        $timestamp = now();
        $rows = [];
        foreach ($missing as $code => [$name, $type, $order]) {
            $rows[] = [
                'code' => $code, 'name' => $name, 'type' => $type, 'sort_order' => $order,
                'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ];
        }
        DB::table('financial_groups')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Restored groups may already be used by a COA or guarantor.
    }
};
