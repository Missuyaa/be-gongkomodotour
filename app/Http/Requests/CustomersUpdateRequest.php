<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomersUpdateRequest extends FormRequest
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
            'user_id' => 'sometimes|required|integer|exists:users,id',
            'alamat' => 'sometimes|required|string|max:255',
            'no_hp' => 'sometimes|required|string|max:20',
            'nasionality' => 'sometimes|required|string|max:50',
            'region' => 'sometimes|required|string|max:50',
            'status' => 'sometimes|required|string|in:Aktif, Non Aktif',
        ];
    }
}