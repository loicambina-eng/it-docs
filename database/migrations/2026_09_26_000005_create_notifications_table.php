<?php
// Role du fichier: Migration de creation de la table notifications.
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('notifications', function(Blueprint $table){$table->id();$table->foreignId('utilisateur_id')->constrained('users');$table->string('type',50);$table->text('message');$table->boolean('lu')->default(false);$table->timestamps();}); } public function down(): void {Schema::dropIfExists('notifications');}};
