<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'alamat' => 'required|string|max:255',
            'no_hp' => 'required|string|max:20',
            'nasionality' => 'required|string|max:50',
            'region' => 'required|string|max:50',
            'status' => 'required|string|in:Aktif, Non Aktif',
        ];
    }
}
