<?php
// Role du fichier: Migration de creation de la table types_documents.
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('types_documents', function(Blueprint $table){$table->id();$table->string('libelle',100)->unique();$table->text('description')->nullable();$table->timestamps();}); } public function down(): void {Schema::dropIfExists('types_documents');}};
