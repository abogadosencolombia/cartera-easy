<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ProcesoController extends Controller
{
    /**
     * Muestra el formulario de importación.
     */
    public function importForm(): Response
    {
        return Inertia::render('Procesos/Import');
    }

    /**
     * Recibe el archivo y (por ahora) lo guarda en storage/app/imports.
     * Aquí luego puedes conectar tu lógica real de lectura (Excel).
     */
    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'], // hasta 10MB
        ]);

        // Guarda el archivo en storage/app/imports
        $path = $request->file('file')->store('imports');

        // TODO: lectura real del Excel y persistencia (Maatwebsite\Excel o PhpSpreadsheet)
        // Excel::import(new ProcesosImport, $request->file('file'));

        return back()->with('success', 'Archivo importado correctamente. Ruta: '.$path);
    }
}
