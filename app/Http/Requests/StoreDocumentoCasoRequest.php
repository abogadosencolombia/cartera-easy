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
            'tipo_documento' => ['required', Rule::in(['pagaré', 'carta instrucciones', 'certificación saldo', 'libranza', 'cédula deudor', 'cédula codeudor', 'otros'])],
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120', // PDF, Imagen o Word, Máx 5MB
            'fecha_carga' => 'required|date',
        ];
    }
}
