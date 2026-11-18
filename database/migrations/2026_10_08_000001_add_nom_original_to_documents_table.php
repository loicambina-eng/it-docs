<?php
// Role du fichier: Migration ajoutant le nom original des fichiers.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Le nom d'origine est nécessaire pour détecter les dépôts répétés.
        if (!Schema::hasColumn('documents', 'nom_original')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('nom_original', 255)->nullable()->after('nom');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'nom_original')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('nom_original');
            });
        }
    }
};
