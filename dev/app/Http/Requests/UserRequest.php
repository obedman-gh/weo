<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))], 'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:12', 'max:255'], 'role' => ['required', Rule::in(['admin', 'pic', 'team', 'client'])], 'active' => 'required|boolean'];
    }
}
