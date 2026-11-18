<?php
// Role du fichier: Migration des associations entre types et categories.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Persiste les associations configurées manuellement entre un type et sa catégorie.
        Schema::create('type_document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->unique()->constrained('types_documents')->cascadeOnDelete();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_document_categories');
    }
};
