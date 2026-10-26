<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referral_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('normalized_name', 190)->unique();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('referral_recipient_id')->nullable()->constrained('referral_recipients')->restrictOnDelete();
            $table->string('referral_patient_name', 150)->nullable();
            $table->string('referral_medical_record_no', 100)->nullable();
            $table->string('referral_procedure', 2)->nullable();
        });
        Schema::create('referral_recipient_legacy_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_recipient_id')->constrained('referral_recipients')->cascadeOnDelete();
            $table->string('name', 150)->unique();
        });
        // Seed master identities only. Never rewrite historical expenses or their journals.
        foreach (DB::table('expenses')->where('expense_type', 'REFERRAL')->whereNotNull('referral_recipient')->distinct()->pluck('referral_recipient') as $name) {
            $key = \App\Models\ReferralRecipient::normalizeName($name);
            if ($key === '') continue;
            DB::table('referral_recipients')->insertOrIgnore([
                'code'=>'RR-'.Str::upper((string) Str::uuid()), 'name'=>trim($name), 'normalized_name'=>$key,
                'is_active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            DB::table('referral_recipient_legacy_names')->insertOrIgnore([
                'referral_recipient_id'=>DB::table('referral_recipients')->where('normalized_name', $key)->value('id'),
                'name'=>$name,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_recipient_id');
            $table->dropColumn(['referral_patient_name', 'referral_medical_record_no', 'referral_procedure']);
        });
        Schema::dropIfExists('referral_recipient_legacy_names');
        Schema::dropIfExists('referral_recipients');
    }
};
