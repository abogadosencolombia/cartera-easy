<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoCasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['PAGARÉ', 'CARTA INSTRUCCIONES', 'CERTIFICACIÓN SALDO', 'LIBRANZA', 'DEMANDA EN WORD', 'DEMANDA EN PDF', 'MEDIDAS CAUTELARES', 'SUBSANACION', 'MEMORIAL DE SUBSANACION', 'AUTOS', 'MEMORIAL', 'CÉDULA DEUDOR', 'CÉDULA CODEUDOR', 'OTROS'])],
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:131072', // PDF, Imagen o Word, Máx 5MB
            'fecha_carga' => 'required|date',
        ];
    }
}
