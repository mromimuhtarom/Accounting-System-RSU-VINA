<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->enum('type', ['INCOME', 'EXPENSE']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->foreignId('financial_group_id')
                ->nullable()
                ->after('category')
                ->constrained('financial_groups')
                ->nullOnDelete();
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('financial_group_id')
                ->nullable()
                ->after('name')
                ->constrained('financial_groups')
                ->nullOnDelete();
        });

        $groups = [
            ['code' => 'REV_SERVICE', 'name' => 'Pendapatan Pelayanan', 'type' => 'INCOME', 'sort_order' => 10],
            ['code' => 'REV_BPJS', 'name' => 'Pendapatan BPJS', 'type' => 'INCOME', 'sort_order' => 20],
            ['code' => 'REV_GUARANTOR', 'name' => 'Asuransi & Penjamin', 'type' => 'INCOME', 'sort_order' => 30],
            ['code' => 'EXP_HR', 'name' => 'Beban SDM', 'type' => 'EXPENSE', 'sort_order' => 10],
            ['code' => 'EXP_MEDICAL', 'name' => 'Beban Medis', 'type' => 'EXPENSE', 'sort_order' => 20],
            ['code' => 'EXP_OPERATIONAL', 'name' => 'Beban Operasional', 'type' => 'EXPENSE', 'sort_order' => 30],
        ];

        foreach ($groups as $group) {
            DB::table('financial_groups')->updateOrInsert(
                ['code' => $group['code']],
                array_merge($group, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $groupIds = DB::table('financial_groups')->pluck('id', 'code');

        DB::table('guarantors')->where('category', 'SELF_PAY')->update([
            'financial_group_id' => $groupIds['REV_SERVICE'] ?? null,
        ]);
        DB::table('guarantors')->where('category', 'BPJS')->update([
            'financial_group_id' => $groupIds['REV_BPJS'] ?? null,
        ]);
        DB::table('guarantors')->whereIn('category', ['PRIVATE_INSURANCE', 'CORPORATE', 'OTHER'])->update([
            'financial_group_id' => $groupIds['REV_GUARANTOR'] ?? null,
        ]);

        DB::table('expense_categories')->whereIn('code', ['GAJI', 'HONOR'])->update([
            'financial_group_id' => $groupIds['EXP_HR'] ?? null,
        ]);
        DB::table('expense_categories')->whereIn('code', ['TRANSFUSI', 'CT_SCAN', 'OBAT'])->update([
            'financial_group_id' => $groupIds['EXP_MEDICAL'] ?? null,
        ]);
        DB::table('expense_categories')->whereNotIn('code', ['GAJI', 'HONOR', 'TRANSFUSI', 'CT_SCAN', 'OBAT'])->update([
            'financial_group_id' => $groupIds['EXP_OPERATIONAL'] ?? null,
        ]);
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_group_id');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_group_id');
        });

        Schema::dropIfExists('financial_groups');
    }
};
