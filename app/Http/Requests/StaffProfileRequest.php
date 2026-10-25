<?php

namespace App\Http\Requests;

use App\Services\StaffAddressFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin'
            && (!$this->route('staff') || $this->route('staff')->role === 'staff');
    }

    protected function prepareForValidation(): void
    {
        foreach (StaffAddressFormatter::FIELDS as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => StaffAddressFormatter::normalize($field, $value)]);
            }
        }

        $names = [];
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $value = $this->input($field);
            $names[$field] = is_string($value)
                ? trim(preg_replace('/\s+/u', ' ', $value))
                : $value;
        }

        $contact = $this->input('contact_number');
        $this->merge($names + [
            'name' => implode(' ', array_filter($names, fn ($value) => is_string($value) && $value !== '')),
            'contact_number' => is_string($contact) ? preg_replace('/[\s-]+/', '', $contact) : $contact,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30', 'regex:/\A(?:09\d{9}|\+?639\d{9})\z/'],
            'birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('staff')?->id)],
        ];

        $rules['replace_address'] = ['sometimes', 'boolean'];
        foreach (StaffAddressFormatter::FIELDS as $field) {
            $rules[$field] = $this->writesAddress()
                ? ['required', 'string', 'max:255', 'regex:/\A[^\r\n]*\z/u']
                : ['exclude'];
        }

        if ($this->isMethod('post')) {
            $rules['password'] = ['required', 'string', 'confirmed', Password::defaults()];
        }

        return $rules;
    }

    private function writesAddress(): bool
    {
        if ($this->isMethod('post') || $this->boolean('replace_address')) {
            return true;
        }

        // Recognized addresses are directly editable. An update omitting all
        // components is still a profile-only update; partial addresses must validate.
        return StaffAddressFormatter::parse($this->route('staff')?->address) !== null
            && $this->hasAny(StaffAddressFormatter::FIELDS);
    }

    /** Explicit persistence payload: input-only fields never reach User. */
    public function profileData(): array
    {
        $data = $this->safe()->except(array_merge(StaffAddressFormatter::FIELDS, ['replace_address', 'password']));
        if ($this->writesAddress()) {
            $address = StaffAddressFormatter::format($this->validated());
            if ($this->isMethod('post') || $address !== $this->route('staff')?->address) {
                $data['address'] = $address;
            }
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'address_line.regex' => 'Enter the address line on a single line.',
            'barangay.regex' => 'Enter the barangay on a single line.',
            'city_municipality.regex' => 'Enter the city or municipality on a single line.',
            'name.max' => 'The combined full name must not exceed 255 characters.',
            'contact_number.regex' => 'Enter a Philippine mobile number such as 09171234567 or +639171234567.',
            'birthday.before_or_equal' => 'Birthday cannot be in the future.',
            'email.unique' => 'This email is already used by an existing account, including deactivated accounts.',
        ];
    }
}
