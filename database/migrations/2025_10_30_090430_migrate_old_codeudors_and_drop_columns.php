<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// --- INICIO: CORRECCIÓN ---
// Añadimos los modelos que la migración necesita conocer
use App\Models\Caso;
use App\Models\Persona;
use App\Models\Codeudor; 
// --- FIN: CORRECCIÓN ---

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Asegurarnos de que las nuevas tablas existan antes de mover datos
        if (!Schema::hasTable('codeudores') || !Schema::hasTable('caso_codeudor')) {
            // Si no existen (p.ej. en un 'fresh'), no hay nada que migrar/borrar
            return;
        }

        // 2. Encontrar todos los IDs de 'personas' que fueron codeudores
        $personaIds = Caso::whereNotNull('codeudor1_id')
            ->select('codeudor1_id as id')
            ->union(
                Caso::whereNotNull('codeudor2_id')->select('codeudor2_id as id')
            )
            ->distinct()
            ->pluck('id');

        $personasAMigrar = Persona::whereIn('id', $personaIds)->get();
        
        $mapaPersonasACodeudores = []; // [persona_id => nuevo_codeudor_id]

        // 3. Crear registros en la nueva tabla 'codeudores'
        foreach ($personasAMigrar as $persona) {
            $codeudor = Codeudor::firstOrCreate(
                ['numero_documento' => $persona->numero_documento],
                [
                    'nombre_completo' => $persona->nombre_completo,
                    'tipo_documento' => $persona->tipo_documento,
                    'celular' => $persona->celular_1, // Mapeamos celular_1 a celular
                    'correo' => $persona->correo_1, // Mapeamos correo_1 a correo
                    'addresses' => $persona->addresses,
                    'social_links' => $persona->social_links,
                    'created_at' => $persona->created_at,
                    'updated_at' => $persona->updated_at,
                ]
            );
            $mapaPersonasACodeudores[$persona->id] = $codeudor->id;
        }

        // 4. Llenar la tabla pivote 'caso_codeudor'
        $casosAntiguos = Caso::whereNotNull('codeudor1_id')
            ->orWhereNotNull('codeudor2_id')
            ->get(['id', 'codeudor1_id', 'codeudor2_id']);
        
        $pivoteData = [];
        foreach ($casosAntiguos as $caso) {
            if ($caso->codeudor1_id && isset($mapaPersonasACodeudores[$caso->codeudor1_id])) {
                $pivoteData[] = [
                    'caso_id' => $caso->id,
                    'codeudor_id' => $mapaPersonasACodeudores[$caso->codeudor1_id]
                ];
            }
            if ($caso->codeudor2_id && isset($mapaPersonasACodeudores[$caso->codeudor2_id])) {
                // Evitar duplicados si codeudor1 y 2 eran la misma persona
                if (!isset($pivoteData[$caso->id . '-' . $caso->codeudor2_id])) {
                     $pivoteData[] = [
                        'caso_id' => $caso->id,
                        'codeudor_id' => $mapaPersonasACodeudores[$caso->codeudor2_id]
                    ];
                }
            }
        }
        
        // Insertar en la tabla pivote
        if (!empty($pivoteData)) {
            DB::table('caso_codeudor')->insertOrIgnore($pivoteData);
        }

        // 5. Finalmente, eliminar las columnas antiguas de 'casos'
        Schema::table('casos', function (Blueprint $table) {
            // Primero eliminamos las llaves foráneas si existen
            // Los nombres de las llaves pueden variar, ajusta si es necesario
            try {
                // Nombres comunes para llaves foráneas
                $table->dropForeign(['codeudor1_id']);
                $table->dropForeign(['codeudor2_id']);
            } catch (\Exception $e) {
                // Ignorar si las llaves no existen o tienen otro nombre
            }
            
            $table->dropColumn(['codeudor1_id', 'codeudor2_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // Añadir las columnas de vuelta
            $table->foreignId('codeudor1_id')->nullable()->constrained('personas')->onDelete('set null');
            $table->foreignId('codeudor2_id')->nullable()->constrained('personas')->onDelete('set null');
        });

        // (Opcional: eliminar datos de 'caso_codeudor', aunque no es perfecto)
        // DB::table('caso_codeudor')->truncate();
        // DB::table('codeudores')->truncate(); 
    }
};
