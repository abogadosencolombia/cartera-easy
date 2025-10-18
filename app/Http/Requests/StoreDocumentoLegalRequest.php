<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoLegalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['Poder', 'Certificado Existencia', 'Carta Autorización', 'Protocolo Interno'])],
            // La regla 'file' asegura que es un archivo. 'mimes' restringe los tipos de archivo permitidos.
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048', // max:2048 significa 2MB
            'fecha_expedicion' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_expedicion',
        ];
    }
}
