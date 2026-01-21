<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveDigitalFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Payload' => ['required', 'array'],
            'Strokes' => ['sometimes', 'array'],
            'FormVersion' => ['sometimes', 'string'],
            'Status' => ['sometimes', 'string'],
        ];
    }
}
