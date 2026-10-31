<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->softDeletes();
            $t->unsignedInteger('lock_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->dropSoftDeletes();
            $t->dropColumn('lock_version');
        });
    }
};
