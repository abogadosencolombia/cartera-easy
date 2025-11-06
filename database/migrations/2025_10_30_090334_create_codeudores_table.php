<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Esta es tu nueva "base de datos de codeudores".
     */
    public function up(): void
    {
        Schema::create('codeudores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_completo');
            $table->string('tipo_documento')->default('CC');
            // Esta es la llave "inteligente" que usaremos
            $table->string('numero_documento')->unique(); 
            
            $table->string('celular')->nullable();
            $table->string('correo')->nullable();
            
            // Usamos JSONB para las direcciones (ilimitadas)
            $table->jsonb('addresses')->nullable();
            // Usamos JSON para las redes (ilimitadas)
            $table->json('social_links')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Buena práctica
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codeudores');
    }
};
