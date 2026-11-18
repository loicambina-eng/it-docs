<?php
// Role du fichier: Migration activant la suppression logique des types.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('types_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
