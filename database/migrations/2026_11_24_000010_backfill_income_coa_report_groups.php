<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $groups = DB::table('financial_groups')->where('type', 'INCOME')->where('is_active', true)
                ->whereIn('code', ['REV_SERVICE', 'REV_BPJS', 'REV_GUARANTOR'])->pluck('id', 'code');

            // Use the legacy report classification as it existed when this migration was written.
            $classification = "CASE
                WHEN p.is_bpjs = 1 OR p.category = 'BPJS' THEN 'REV_BPJS'
                WHEN p.category IN ('PRIVATE_INSURANCE', 'CORPORATE', 'OTHER') THEN 'REV_GUARANTOR'
                ELSE 'REV_SERVICE'
            END";
            $unanimous = DB::table('receipts as r')
                ->join('chart_accounts as a', 'a.id', '=', 'r.chart_account_id')
                ->leftJoin('guarantors as p', 'p.id', '=', 'r.guarantor_id')
                ->where('a.type', 'REVENUE')->whereNull('a.financial_group_id')->where('r.status', 'ACTIVE')
                ->select('a.id')->selectRaw("MIN($classification) AS group_code")
                ->groupBy('a.id')->havingRaw("COUNT(DISTINCT $classification) = 1")->get();

            foreach ($unanimous->groupBy('group_code') as $code => $accounts) {
                if ($groups->has($code)) {
                    DB::table('chart_accounts')->whereIn('id', $accounts->pluck('id'))
                        ->whereNull('financial_group_id')->update(['financial_group_id' => $groups[$code]]);
                }
            }
        }, 3);
    }

    public function down(): void
    {
        // Keep assignments that may have been used or manually edited since the migration.
    }
};
