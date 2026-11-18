<?php
// Role du fichier: Migration ajoutant la date archivage aux documents.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('documents', 'date_archivage')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dateTime('date_archivage')->nullable()->after('date_precision');
            });
        }
        DB::table('documents')->where('statut', 'archive')->whereNull('date_archivage')->update([
            'date_archivage' => DB::raw('COALESCE(created_at, updated_at)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'date_archivage')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('date_archivage');
            });
        }
    }
};
