<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('bpjs_claims', function(Blueprint $t){
   $t->id(); $t->string('sep_no',60)->unique(); $t->string('patient_name',150); $t->string('medical_record_no',50)->nullable(); $t->date('service_date');
   $t->enum('service_type',['RAWAT_JALAN','RAWAT_INAP']); $t->string('batch_no',100)->nullable(); $t->date('submitted_at')->nullable();
   $t->decimal('hospital_tariff',18,2)->nullable(); $t->decimal('claimed_amount',18,2); $t->decimal('approved_amount',18,2)->default(0);
   $t->decimal('rejected_amount',18,2)->default(0); $t->string('status',30)->default('DRAFT'); $t->text('notes')->nullable();
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps(); $t->index(['service_date','status']);
  });
  Schema::create('bpjs_claim_events',function(Blueprint $t){
   $t->id(); $t->foreignId('bpjs_claim_id')->constrained('bpjs_claims')->cascadeOnDelete(); $t->string('event_type',30); $t->decimal('old_approved',18,2)->nullable(); $t->decimal('new_approved',18,2)->nullable(); $t->string('old_status',30)->nullable(); $t->string('new_status',30)->nullable(); $t->text('reason')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
  });
  Schema::create('bpjs_claim_payments',function(Blueprint $t){
   $t->id(); $t->foreignId('bpjs_claim_id')->constrained('bpjs_claims')->restrictOnDelete(); $t->foreignId('receipt_id')->unique()->constrained('receipts')->restrictOnDelete(); $t->decimal('amount',18,2); $t->timestamps();
  });
 }
 public function down():void { Schema::dropIfExists('bpjs_claim_payments');Schema::dropIfExists('bpjs_claim_events');Schema::dropIfExists('bpjs_claims'); }
};
