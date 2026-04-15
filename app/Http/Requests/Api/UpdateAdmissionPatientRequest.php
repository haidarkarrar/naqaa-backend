<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdmissionPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'DOB' => ['sometimes', 'nullable', 'date'],
            'Mother' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'Diabetic' => ['sometimes', 'boolean'],
            'Pregnancy' => ['sometimes', 'boolean'],
            'CardiacFailure' => ['sometimes', 'boolean'],
            'RenalFailure' => ['sometimes', 'boolean'],
            'OtherDisease' => ['sometimes', 'boolean'],
            'ChecklistItemIds' => ['sometimes', 'array'],
            'ChecklistItemIds.*' => ['integer', 'distinct', Rule::exists('meditop.TblCheckListItems', 'Id')],
        ];
    }
}
