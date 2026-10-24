<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $app = DB::table('applications')->where('code','ACCOUNTING')->value('id');
        if ($app) {
            $old = DB::table('roles')->where('application_id',$app)->where('code','CASEMIX')->first();
            $new = DB::table('roles')->where('application_id',$app)->where('code','ASURANSI')->first();
            if ($old && !$new) DB::table('roles')->where('id',$old->id)->update(['code'=>'ASURANSI','name'=>'Petugas Asuransi','updated_at'=>now()]);
            elseif ($old && $new) DB::table('user_roles')->where('role_id',$old->id)->update(['role_id'=>$new->id]);
            elseif (!$new) DB::table('roles')->insert(['application_id'=>$app,'code'=>'ASURANSI','name'=>'Petugas Asuransi','created_at'=>now(),'updated_at'=>now()]);
        }
        if (!Schema::hasTable('insurance_monthly_claims')) Schema::create('insurance_monthly_claims', function (Blueprint $t) {
            $t->id(); $t->foreignId('guarantor_id')->constrained('guarantors')->restrictOnDelete();
            $t->unsignedSmallInteger('year'); $t->unsignedTinyInteger('month');
            $t->enum('service_type',['RAWAT_JALAN','RAWAT_INAP']);
            $t->decimal('claimed_amount',18,2); $t->decimal('approved_amount',18,2)->nullable();
            $t->decimal('paid_amount',18,2)->default(0); $t->string('status',24)->default('DIAJUKAN');
            $t->date('submitted_at')->nullable(); $t->date('settled_at')->nullable();
            $t->string('reference_no',120)->nullable(); $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable(); $t->foreignId('updated_by')->nullable();
            $t->timestamps(); $t->unique(['guarantor_id','year','month','service_type'],'insurance_claim_period_unique');
            $t->index(['year','month','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('insurance_monthly_claims'); /* Do not reverse role changes automatically. */ }
};
