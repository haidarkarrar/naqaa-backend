<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchAdmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:selected,filtered'],
            'status' => ['required', 'string', 'in:open,closed'],
            'admission_ids' => ['required_if:mode,selected', 'prohibited_unless:mode,selected', 'array', 'min:1', 'max:500'],
            'admission_ids.*' => ['integer', 'distinct', 'min:1'],
            'filters' => ['sometimes', 'prohibited_unless:mode,filtered', 'array'],
            'filters.status' => ['sometimes', 'string', 'in:open,closed'],
            'filters.start_date' => ['sometimes', 'date_format:Y-m-d'],
            'filters.end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:filters.start_date'],
            'filters.patient' => ['sometimes', 'string', 'max:255'],
        ];
    }
}

