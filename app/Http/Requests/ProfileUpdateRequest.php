<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User hanya bisa update profilnya sendiri
        $userId = $this->route('id');
        
        // Jika tidak ada ID di route, berarti menggunakan ID user yang sedang login
        if (!$userId) {
            return true;
        }
        
        return $this->user() && $this->user()->id == $userId;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            // User fields
            'name' => 'sometimes|required|string|max:50|min:3',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:50',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => 'sometimes|required|string|min:8|confirmed',
            'password_confirmation' => 'sometimes|required_with:password|string|min:8|same:password',
            
            // Customer fields
            'alamat' => 'sometimes|required|string|max:255',
            'no_hp' => 'sometimes|required|string|max:20',
            'nasionality' => 'sometimes|required|string|max:50',
            'region' => 'sometimes|required|string|max:50',
        ];
    }
}

