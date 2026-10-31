<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_imports', function (Blueprint $t) {
            $t->id();
            $t->string('filename');
            $t->string('file_hash', 64)->unique();
            $t->string('path');
            $t->string('source_type')->nullable();
            $t->json('worksheets');
            $t->json('mapping')->nullable();
            $t->string('sheet')->nullable();
            $t->unsignedInteger('header_row')->nullable();
            $t->string('status')->default('UPLOADED');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_staging_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_import_id')->constrained()->cascadeOnDelete();
            $t->string('sheet');
            $t->unsignedInteger('source_row');
            $t->json('raw');
            $t->json('normalized');
            $t->string('fingerprint', 64)->index();
            $t->string('status')->index();
            $t->json('issues');
            $t->json('duplicates');
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['asset_import_id', 'sheet', 'source_row'], 'asset_staging_origin_unique');
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->uuid('qr_token')->unique();
            $t->foreignId('asset_staging_row_id')->unique()->constrained();
            $t->string('fingerprint', 64)->index();
            foreach (['name', 'code', 'serial_number', 'brand', 'model', 'category', 'location', 'condition', 'funding', 'custodian', 'capacity'] as $field) {
                $t->string($field)->nullable();
            }
            $t->string('inventory_type')->index();
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedSmallInteger('procurement_year')->nullable();
            $t->decimal('source_price', 18, 2)->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('ACTIVE')->index();
            $t->boolean('calibration_required')->default(false);
            $t->date('calibration_due_at')->nullable()->index();
            $t->foreignId('verified_by')->constrained('users');
            $t->timestamp('verified_at');
            $t->timestamps();
        });
        Schema::create('asset_periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status')->default('OPEN');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('asset_capitalizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->unique()->constrained();
            $t->decimal('cost', 18, 2);
            $t->date('acquired_at');
            $t->date('depreciation_starts_at');
            $t->unsignedInteger('useful_life_months');
            $t->decimal('residual_value', 18, 2);
            $t->decimal('opening_accumulated', 18, 2);
            $t->decimal('opening_book_value', 18, 2);
            foreach (['asset_account_id', 'expense_account_id', 'accumulated_account_id', 'offset_account_id'] as $f) {
                $t->foreignId($f)->constrained('chart_accounts');
            }
            $t->string('status')->default('DRAFT');
            $t->text('evidence');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $t->timestamps();
        });
        Schema::create('asset_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained();
            $t->string('type')->index();
            $t->date('event_date');
            $t->json('data');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('asset_audits', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->foreignId('actor_id')->constrained('users');
            $t->timestamp('created_at');
            $t->index(['subject_type', 'subject_id']);
        });
        $app = DB::table('applications')->where('code', 'ACCOUNTING')->value('id');
        if ($app) {
            foreach (['ADMIN_ASET' => 'Administrator Aktiva', 'PETUGAS_ASET' => 'Petugas Inventaris'] as $code => $name) {
                DB::table('roles')->insertOrIgnore(['application_id' => $app, 'code' => $code, 'name' => $name]);
            }
        }
    }

    public function down(): void
    {
        foreach (['asset_audits', 'asset_events', 'asset_capitalizations', 'asset_periods', 'assets', 'asset_staging_rows', 'asset_imports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
