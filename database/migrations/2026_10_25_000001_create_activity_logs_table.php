<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Historical identities deliberately survive removal of users and source records.
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name');
            $table->string('actor_email')->nullable();
            $table->string('actor_roles')->nullable();
            $table->string('action', 20)->index();
            $table->string('menu', 100)->index();
            $table->string('form_name', 100)->index();
            $table->string('route_name')->nullable();
            $table->string('subject_type');
            $table->string('subject_id', 100);
            $table->string('subject_label');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_fields');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('activity_logs'); }
};
