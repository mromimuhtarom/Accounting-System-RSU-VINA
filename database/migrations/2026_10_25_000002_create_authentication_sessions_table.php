<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authentication_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('session_hash', 64)->unique();
            $table->unsignedBigInteger('actor_id')->index();
            $table->string('actor_name');
            $table->string('actor_email')->nullable();
            $table->string('actor_roles')->nullable();
            $table->string('device_name');
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('logged_in_at')->nullable();
            $table->dateTime('last_activity_at');
            $table->dateTime('expires_at')->index();
            $table->dateTime('logged_out_at')->nullable()->index();
            $table->string('logout_reason', 30)->nullable();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('authentication_session_id')->nullable()->index();
            $table->string('device_name')->nullable();
            $table->text('user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['authentication_session_id']);
            $table->dropColumn(['authentication_session_id', 'device_name', 'user_agent']);
        });
        Schema::dropIfExists('authentication_sessions');
    }
};
