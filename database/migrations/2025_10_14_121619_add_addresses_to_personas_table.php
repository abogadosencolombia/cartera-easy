<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
	{
    	Schema::table('personas', function (Blueprint $table) {
        $table->jsonb('addresses')->nullable()->after('social_links'); // Añade la columna
    	});
	}

	// El método down() es para revertir los cambios
	public function down(): void
	{
    	Schema::table('personas', function (Blueprint $table) {
        $table->dropColumn('addresses'); // Elimina la columna
    	});
	}
};
