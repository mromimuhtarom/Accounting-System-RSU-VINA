<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_profiles', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();
            $t->string('name', 60);
            $t->string('photo_path')->nullable();
            $t->string('photo_mime', 40)->nullable();
            $t->unsignedInteger('lock_version')->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        DB::table('assistant_profiles')->insert(['id' => 1, 'name' => 'Sukoco', 'lock_version' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_profiles');
    }
};
