<?php

use App\Services\StaffAddressFormatter;

it('recognizes only canonical three-line staff addresses without guessing', function () {
    $parts = ['address_line' => '12 Road, Village', 'barangay' => 'San Jose', 'city_municipality' => 'Santa Maria'];
    expect(StaffAddressFormatter::parse(StaffAddressFormatter::format($parts)))->toBe($parts);
    foreach ([null, '', '12 Road, San Jose, Santa Maria', "12 Road\nSan Jose\nSanta Maria", "12 Road\r\nBarangay San Jose\r\nSanta Maria", "12 Road\nBarangay Barangay San Jose\nSanta Maria", "12 Road\nBarangay San Jose\nSanta Maria\nProvince", "12 Road\nBarangay \nSanta Maria"] as $legacy) {
        expect(StaffAddressFormatter::parse($legacy))->toBeNull();
    }
});

it('normalizes the barangay prefix without changing ordinary location names', function () {
    expect(StaffAddressFormatter::normalize('barangay', ' barangay Barangay San Jose '))->toBe('San Jose')
        ->and(StaffAddressFormatter::normalize('barangay', 'Barangayan'))->toBe('Barangayan')
        ->and(StaffAddressFormatter::normalize('address_line', '  12 Road, Village  '))->toBe('12 Road, Village');
});
