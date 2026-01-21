<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'File' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png'],
            'Label' => ['sometimes', 'string'],
        ];
    }
}
