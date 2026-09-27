<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailChanged = strtolower((string) $this->input('email'))
            !== strtolower((string) $this->user()->email);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'current_password' => [
                Rule::requiredIf($emailChanged),
                'nullable',
                'current_password',
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your full name is required.',
            'name.max' => 'Your full name must not exceed 255 characters.',

            'email.required' => 'Your email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already being used by another account.',
            'email.max' => 'Your email address must not exceed 255 characters.',

            'current_password.required' => 'Enter your current password to change your email address.',
            'current_password.current_password' => 'The current password you entered is incorrect.',
        ];
    }
}