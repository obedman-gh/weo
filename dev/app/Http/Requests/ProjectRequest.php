<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:160', 'couple_name' => 'required|string|max:160', 'bride_name' => 'required|string|max:100', 'groom_name' => 'required|string|max:100', 'event_date' => 'required|date_format:Y-m-d', 'venue' => 'required|string|max:255', 'timezone' => 'required|timezone', 'status' => ['required', Rule::in(['persiapan', 'berjalan', 'selesai', 'arsip'])], 'member_ids' => 'required|array|min:1', 'member_ids.*' => ['integer', Rule::exists('users', 'id')->where('active', true)], 'template_id' => 'nullable|exists:checklist_templates,id'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (is_array($this->member_ids) && User::whereIn('id', $this->member_ids)->where('role', 'client')->count() > 2) {
                $validator->errors()->add('member_ids', 'Satu proyek maksimal memiliki dua akun pasangan.');
            }
        });
    }
}
