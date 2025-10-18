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
            Schema::table('proceso_radicados', function (Blueprint $table) {
                $table->enum('estado', ['ACTIVO', 'CERRADO'])->default('ACTIVO')->after('radicado');
                $table->text('nota_cierre')->nullable()->after('observaciones');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('proceso_radicados', function (Blueprint $table) {
                $table->dropColumn('estado');
                $table->dropColumn('nota_cierre');
            });
        }
    };
    
