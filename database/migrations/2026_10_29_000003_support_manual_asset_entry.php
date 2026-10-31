<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->unsignedBigInteger('asset_staging_row_id')->nullable()->change();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->uuid('entry_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Keep nullable source links: manual records must survive a rollback.
        Schema::table('assets', function (Blueprint $t) {
            $t->dropForeign(['created_by']);
            $t->dropColumn('created_by');
            $t->dropUnique(['entry_token']);
            $t->dropColumn('entry_token');
        });
    }
};
