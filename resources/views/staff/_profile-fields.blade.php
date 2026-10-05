@php
    $staff = $staff ?? null;
    $profileFields = [
        'first_name' => ['First Name', 'text', 100, true],
        'middle_name' => ['Middle Name (optional)', 'text', 100, false],
        'last_name' => ['Last Name', 'text', 100, true],
        'contact_number' => ['Contact Number', 'tel', 30, true],
        'birthday' => ['Birthday', 'date', null, true],
        'email' => ['Email', 'email', 255, true],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4" style="min-width: 0; overflow-wrap: anywhere;">
    @foreach ($profileFields as $field => [$label, $type, $limit, $required])
        <div class="min-w-0">
            <label for="staff-{{ $field }}" class="staff-label block text-sm font-medium text-gray-700">
                {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
            </label>
            <input id="staff-{{ $field }}" type="{{ $type }}" name="{{ $field }}"
                value="{{ old($field, $field === 'birthday' ? $staff?->birthday?->toDateString() : $staff?->{$field}) }}"
                @if ($required) required @endif
                @if ($limit) maxlength="{{ $limit }}" @endif
                @if ($field === 'birthday') max="{{ today()->toDateString() }}" @endif
                @if ($field === 'contact_number') autocomplete="tel" placeholder="09171234567 or +639171234567" @endif
                class="staff-input mt-1 block w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @if ($field === 'contact_number')
                <p class="mt-1 text-xs text-gray-500">Philippine mobile number; spaces and hyphens are allowed.</p>
            @endif
            <x-input-error :messages="$errors->get($field)" class="mt-1 break-words" />
        </div>
    @endforeach
    <div class="min-w-0 sm:col-span-2">
        <x-input-error :messages="$errors->get('name')" class="mt-1 break-words" />
        <label for="staff-address" class="staff-label block text-sm font-medium text-gray-700">Address <span class="text-red-500">*</span></label>
        <textarea id="staff-address" name="address" rows="3" required maxlength="1000"
            class="staff-input mt-1 block w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address', $staff?->address) }}</textarea>
        <x-input-error :messages="$errors->get('address')" class="mt-1 break-words" />
    </div>
</div>
