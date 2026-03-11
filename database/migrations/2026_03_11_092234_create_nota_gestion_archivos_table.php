<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nota_gestion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_gestion_id')->constrained('nota_gestions')->onDelete('cascade');
            $table->string('nombre_original');
            $table->string('ruta_archivo');
            $table->string('mime_type')->nullable();
            $table->integer('size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_gestion_archivos');
    }
};
