<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:5120', // optional: limit to 5MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload an Excel file.',
            'file.mimes' => 'The file must be a .xlsx Excel file.',
            'file.max' => 'The Excel file must not exceed 5MB.',
        ];
    }
}
