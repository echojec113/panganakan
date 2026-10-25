<?php

namespace App\Services;

/** Staff's three input components are stored in its existing address column. */
class StaffAddressFormatter
{
    public const FIELDS = ['address_line', 'barangay', 'city_municipality'];

    public static function normalize(string $field, string $value): string
    {
        $value = trim($value);

        return $field === 'barangay'
            ? trim(preg_replace('/\A(?:Barangay(?:\h+|$))+/iu', '', $value))
            : $value;
    }

    /** Call only with validated, nonempty, single-line components. */
    public static function format(array $components): string
    {
        return $components['address_line']."\nBarangay ".$components['barangay']."\n".$components['city_municipality'];
    }

    /** Recognize only an exact canonical round trip; never split free-form commas. */
    public static function parse(?string $address): ?array
    {
        if (!is_string($address) || !preg_match('/\A([^\r\n]+)\nBarangay ([^\r\n]+)\n([^\r\n]+)\z/u', $address, $matches)) {
            return null;
        }

        $components = [];
        foreach (self::FIELDS as $index => $field) {
            $value = self::normalize($field, $matches[$index + 1]);
            if ($value === '' || mb_strlen($value) > 255) {
                return null;
            }
            $components[$field] = $value;
        }

        return self::format($components) === $address ? $components : null;
    }
}
