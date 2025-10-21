// app/Http/Controllers/JuzgadoController.php

namespace App\Http\Controllers;

use App\Models\Juzgado;
use Illuminate\Http\Request;

class JuzgadoController extends Controller
{
    /**
     * Busca juzgados de forma flexible por palabras clave.
     */
    public function search(Request $request)
    {
        $request->validate([
            'term' => 'nullable|string|max:100',
        ]);

        $searchTerm = $request->input('term', '');

        if (empty($searchTerm)) {
            return response()->json(Juzgado::limit(20)->get());
        }

        $keywords = explode(' ', $searchTerm);
        $query = Juzgado::query();

        foreach ($keywords as $keyword) {
            if (!empty($keyword)) {
                // *** El cambio clave está aquí ***
                $query->where('nombre', 'ILIKE', '%' . $keyword . '%');
            }
        }

        $juzgados = $query->limit(50)->get();

        return response()->json($juzgados);
    }
}