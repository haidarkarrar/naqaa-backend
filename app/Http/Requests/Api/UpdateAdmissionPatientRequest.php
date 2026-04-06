<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
            'Smoker' => ['sometimes', 'boolean'],
            'Alcoholic' => ['sometimes', 'boolean'],
            'Allergies' => ['sometimes', 'boolean'],
            'Diabetic' => ['sometimes', 'boolean'],
            'Pregnancy' => ['sometimes', 'boolean'],
            'CardiacFailure' => ['sometimes', 'boolean'],
            'RenalFailure' => ['sometimes', 'boolean'],
            'OtherDisease' => ['sometimes', 'boolean'],
            'MedicalHistory' => ['sometimes', 'nullable', 'string'],
            'SurgicalHistory' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
