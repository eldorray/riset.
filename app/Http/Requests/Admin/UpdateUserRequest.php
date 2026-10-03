<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['pengguna', 'admin'])],
            'password' => ['nullable', 'string', Password::min(8), 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['password.min' => 'Password minimal 8 karakter.'];
    }

    /**
     * Admin tidak bisa mencabut peran admin dirinya sendiri (agar panel tidak terkunci).
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->route('user');

            if ($target instanceof User && $target->is($this->user()) && $this->input('role') !== 'admin') {
                $validator->errors()->add('role', 'Anda tidak bisa mencabut peran admin akun Anda sendiri.');
            }
        }];
    }
}
