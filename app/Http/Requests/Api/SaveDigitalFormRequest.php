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
            'Strokes.*.id' => ['sometimes', 'string'],
            'Strokes.*.tool' => ['required_with:Strokes', 'string', 'in:pen,eraser'],
            'Strokes.*.width' => ['required_with:Strokes', 'numeric', 'min:1'],
            'Strokes.*.color' => ['sometimes', 'string'],
            'Strokes.*.points' => ['required_with:Strokes', 'array', 'min:1'],
            'Strokes.*.points.*.x' => ['required_with:Strokes', 'numeric'],
            'Strokes.*.points.*.y' => ['required_with:Strokes', 'numeric'],
            'Strokes.*.points.*.timestamp' => ['sometimes', 'numeric'],
            'FormVersion' => ['sometimes', 'string'],
            'Status' => ['sometimes', 'string'],
        ];
    }
}
