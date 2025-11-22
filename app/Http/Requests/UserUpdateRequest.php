<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
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
        // Ambil ID user dari parameter route (route menggunakan {id})
        $userId = $this->route('id') ?? $this->route('user');

        // Jika userId tidak ada, return rules dasar tanpa validasi user spesifik
        if (!$userId) {
            return [
                'name' => 'sometimes|required|string|max:50|min:3',
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:50',
                ],
                'password' => 'sometimes|required|string|min:8|confirmed',
                'password_confirmation' => 'sometimes|required_with:password|string|min:8|same:password',
                'role' => 'sometimes|required|string|exists:roles,name',
                'status' => 'sometimes|required|in:Aktif,Non Aktif',
            ];
        }

        // Konversi ke integer untuk konsistensi
        $userId = (int) $userId;

        // Gunakan caching untuk mengambil user dengan role-nya
        try {
            $user = Cache::remember("user_{$userId}_with_roles", 3600, function () use ($userId) {
                return User::with('roles')->findOrFail($userId);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Jika user tidak ditemukan, return rules dasar
            $user = null;
        }

        return [
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
            'role' => 'sometimes|required|string|exists:roles,name',
            'status' => [
                'sometimes',
                'required',
                'in:Aktif,Non Aktif',
                function ($attribute, $value, $fail) use ($user) {
                    // Validasi jika user memiliki role "Admin", status tidak bisa diubah menjadi Non Aktif
                    if ($user && $user->hasRole('Admin') && $value === 'Non Aktif') {
                        $fail('User dengan role Admin tidak dapat di-nonaktifkan.');
                    }
                },
            ],
        ];
    }
}
