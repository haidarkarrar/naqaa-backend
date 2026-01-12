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
            'payload' => ['required', 'array'],
            'strokes' => ['sometimes', 'array'],
            'form_version' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
        ];
    }
}
