<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('authentication_sessions', function (Blueprint $table) {
            $table->boolean('keep_signed_in')->default(false);
            $table->char('persistent_token_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('authentication_sessions', function (Blueprint $table) {
            $table->dropUnique(['persistent_token_hash']);
            $table->dropColumn(['keep_signed_in', 'persistent_token_hash']);
        });
    }
};
