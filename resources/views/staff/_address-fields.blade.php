@php
    $editingAddress = isset($staff) && $staff?->exists;
    $addressParts = $editingAddress ? \App\Services\StaffAddressFormatter::parse($staff->address) : null;
    $replaceAddress = !$editingAddress || $addressParts !== null || in_array(old('replace_address', '0'), ['1', 1, true], true);
    $addressFields = [
        'address_line' => ['Address Line', 'House/Unit/Bldg No., Street Name, Subdivision/Village'],
        'barangay' => ['Barangay', 'Barangay name'],
        'city_municipality' => ['City / Municipality', 'City or Municipality'],
    ];
@endphp

<section data-staff-address class="min-w-0 sm:col-span-2">
    <h3 class="text-sm font-medium text-[#19355F] mb-3">Address Information</h3>
    @if ($editingAddress && $addressParts === null)
        <p class="text-sm text-gray-700">Saved address</p>
        <p class="whitespace-pre-line break-words rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ $staff->address ?: 'No address recorded.' }}</p>
        <p id="staff-address-help" class="mt-2 text-xs text-gray-500">
            Your saved address will be kept as entered. To replace it, enter all three address fields below.
        </p>
        <label class="my-3 flex items-center gap-2 text-sm font-medium text-[#19355F]">
            <input type="checkbox" name="replace_address" value="1" @checked($replaceAddress)
                aria-describedby="staff-address-help" aria-controls="staff-address-components">
            Replace saved address
        </label>
        <x-input-error :messages="$errors->get('replace_address')" class="mt-1" />
    @endif
    <fieldset id="staff-address-components" @disabled(!$replaceAddress) class="grid grid-cols-1 sm:grid-cols-2 gap-4 disabled:opacity-60">
        <legend class="sr-only">Address components</legend>
        @foreach ($addressFields as $field => [$label, $placeholder])
            @php
                $addressValue = old($field, $addressParts[$field] ?? '');
                $addressValue = is_string($addressValue) ? $addressValue : '';
            @endphp
            <div class="min-w-0 {{ $field === 'address_line' ? 'sm:col-span-2' : '' }}">
                <label for="staff-{{ $field }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} <span class="text-red-500">*</span></label>
                <input id="staff-{{ $field }}" type="text" name="{{ $field }}" required maxlength="255"
                    value="{{ $addressValue }}" placeholder="{{ $placeholder }}"
                    data-address-label="{{ $label }}" aria-describedby="staff-{{ $field }}-error"
                    @error($field) aria-invalid="true" @enderror
                    class="w-full min-w-0 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition @error($field) border-red-500 @enderror">
                <div id="staff-{{ $field }}-error" aria-live="polite">
                    <span data-address-error class="hidden text-red-500 text-xs mt-1"></span>
                    <x-input-error :messages="$errors->get($field)" class="mt-1 break-words" />
                </div>
            </div>
        @endforeach
    </fieldset>
</section>
