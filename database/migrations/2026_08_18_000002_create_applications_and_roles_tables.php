<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{
Schema::create('applications',function(Blueprint $table){$table->id();$table->string('code',50)->unique();$table->string('name');$table->boolean('is_active')->default(true);$table->timestamps();});
Schema::create('roles',function(Blueprint $table){$table->id();$table->foreignId('application_id')->constrained()->cascadeOnDelete();$table->string('code',50);$table->string('name');$table->timestamps();$table->unique(['application_id','code']);});
Schema::create('user_roles',function(Blueprint $table){$table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->foreignId('role_id')->constrained()->cascadeOnDelete();$table->timestamps();$table->unique(['user_id','role_id']);});
} public function down():void{Schema::dropIfExists('user_roles');Schema::dropIfExists('roles');Schema::dropIfExists('applications');} };
