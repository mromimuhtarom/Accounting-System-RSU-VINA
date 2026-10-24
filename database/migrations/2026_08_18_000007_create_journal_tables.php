<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{
Schema::create('journal_entries',function(Blueprint $table){$table->id();$table->string('journal_no',50)->unique();$table->date('journal_date');$table->string('reference',100)->nullable();$table->nullableMorphs('source');$table->text('description');$table->enum('status',['POSTED'])->default('POSTED');$table->foreignId('created_by')->constrained('users')->restrictOnDelete();$table->timestamps();$table->index(['journal_date','status']);});
Schema::create('journal_entry_lines',function(Blueprint $table){$table->id();$table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();$table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();$table->string('description')->nullable();$table->decimal('debit',18,2)->default(0);$table->decimal('credit',18,2)->default(0);$table->timestamps();$table->index('chart_account_id');});
}public function down():void{Schema::dropIfExists('journal_entry_lines');Schema::dropIfExists('journal_entries');}};
