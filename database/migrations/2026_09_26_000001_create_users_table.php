<?php
// Role du fichier: Migration de creation de la table utilisateurs.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('users', function(Blueprint $table){ $table->id(); $table->string('nom',100); $table->string('prenom',100); $table->string('email',150)->unique(); $table->string('telephone',30)->nullable(); $table->string('fonction',100)->nullable(); $table->string('adresse')->nullable(); $table->string('nationalite',100)->nullable(); $table->enum('role',['admin','assistante','ceo']); $table->enum('statut',['actif','inactif'])->default('actif'); $table->boolean('en_ligne')->default(false); $table->timestamp('derniere_activite')->nullable(); $table->string('password'); $table->string('photo')->nullable(); $table->rememberToken(); $table->timestamps(); $table->softDeletes(); }); } public function down(): void { Schema::dropIfExists('users'); }};
