<?php
// Role du fichier: Migration ajoutant la precision des dates et les categories initiales.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table) { $table->string('date_precision', 8)->default('day')->after('date_depot'); });
        Schema::create('document_categories', function (Blueprint $table) { $table->id(); $table->string('name',100)->unique(); $table->timestamps(); });
        foreach (['Contrats','Factures','Courriers','RH','Interventions','Administration','Projets','Autre'] as $name) DB::table('document_categories')->insert(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('documents')->where('categorie','Divers')->update(['categorie'=>'Autre']);
    }
    public function down(): void { Schema::dropIfExists('document_categories'); Schema::table('documents', function (Blueprint $table) { $table->dropColumn('date_precision'); }); }
};
