<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Juzgado;
use App\Models\Persona;
use App\Models\User;
use App\Models\EspecialidadJuridica;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class ProcesoJudicialController extends Controller
{
    public function create(): Response
    {
        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->select('id', 'name')->get();
        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('ProcesosJudiciales/Create', [
            'abogadosYGestores' => $abogadosYGestores,
            'personas' => $personas,
            'juzgados' => Juzgado::orderBy('nombre')->get(['id', 'nombre']),
            'subtipos_proceso' => $this->getSubtiposProceso(),
            'etapas_procesales' => $this->getEtapasProcesales(),
            'especialidades' => EspecialidadJuridica::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validatedData = $this->validateProceso($request);
        $casoData = array_merge($validatedData, ['tipo_caso' => 'judicial']);
        $caso = Caso::create($casoData);

        return redirect()->route('procesos.show', $caso->id)
            ->with('success', '¡Proceso Judicial registrado exitosamente!');
    }

    public function show(Caso $caso): Response
    {
        $caso->load(['demandante', 'demandado', 'user', 'actuaciones.user', 'juzgado_relacionado', 'especialidad']);
        return Inertia::render('ProcesosJudiciales/Show', ['proceso' => $caso]);
    }

    public function edit(Caso $caso): Response
    {
        $abogadosYGestores = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
            ->select('id', 'name')->get();
        $personas = Persona::select('id', 'nombre_completo', 'numero_documento')->get();

        return Inertia::render('ProcesosJudiciales/Edit', [
            'proceso' => $caso,
            'abogadosYGestores' => $abogadosYGestores,
            'personas' => $personas,
            'juzgados' => Juzgado::orderBy('nombre')->get(['id', 'nombre']),
            'subtipos_proceso' => $this->getSubtiposProceso(),
            'etapas_procesales' => $this->getEtapasProcesales(),
            'especialidades' => EspecialidadJuridica::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function update(Request $request, Caso $caso): RedirectResponse
    {
        $validatedData = $this->validateProceso($request);
        $caso->update($validatedData);

        return redirect()->route('procesos.show', $caso->id)
            ->with('success', '¡Proceso Judicial actualizado exitosamente!');
    }

    private function validateProceso(Request $request): array
    {
        return $request->validate([
            'user_id' => 'required|exists:users,id',
            'radicado_externo' => 'nullable|string|max:255',
            'juzgado_id' => 'nullable|exists:juzgados,id',
            'naturaleza_proceso' => 'nullable|string|max:255',
            'asunto' => 'nullable|string',
            'demandante_id' => 'required|exists:personas,id',
            'demandado_id' => 'required|exists:personas,id',
            'link_expediente_digital' => 'nullable|url',
            'correo_juzgado' => 'nullable|email',
            'ubicacion_drive' => 'nullable|string|max:255',
            'tipo_proceso' => 'required|string|max:255',
            'subtipo_proceso' => ['nullable', 'string', Rule::in($this->getSubtiposProceso())],
            'etapa_procesal' => ['nullable', 'string', Rule::in($this->getEtapasProcesales())],
            'especialidad_id' => 'required|exists:especialidades_juridicas,id', // <— NUEVO
        ]);
    }

    private function getSubtiposProceso(): array
    {
        return ['CURADURIA', 'DIVISORIO', 'GARANTIA REAL', 'HIPOTECARIO', 'INSOLVENCIA',
            'LABORAL', 'MIXTO', 'MUEBLE', 'PAGO DIRECTO', 'PRENDARIO', 'SINGULAR', 'SUCESIÓN'];
    }

    private function getEtapasProcesales(): array
    {
        return ['Avalúo y Remate', 'Notificación', 'Contestación Demanda', 'Audiencia Inicial',
            'Audiencia de Instrucción y Juzgamiento', 'Sentencia', 'Apelación', 'Ejecución de Sentencia',
            'Liquidación', 'Terminación'];
    }
}
