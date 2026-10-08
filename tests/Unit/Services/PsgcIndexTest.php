<?php

use App\Services\PsgcIndex;

beforeEach(function () {
    PsgcIndex::flush();
});

function psgcTestIndex(): PsgcIndex
{
    return new PsgcIndex(dirname(__DIR__, 3) . '/resources/data/psgc/psgc.json');
}

it('labels the patient address exactly like the feature specification example', function () {
    $index = psgcTestIndex();

    expect($index->label('0301420061'))->toBe(
        'Muzon South, City of San Jose Del Monte, Bulacan, Central Luzon'
    );
});

it('labels NCR, highly urbanized city and container chains', function () {
    $index = psgcTestIndex();

    expect($index->label('1380100001'))->toBe('Barangay 1, City of Caloocan, National Capital Region (NCR)')
        ->and($index->label('1381701000'))->toBe('Pateros, National Capital Region (NCR)')
        ->and($index->label('0730600000'))->toBe('City of Cebu, Central Visayas')
        ->and($index->label('0990101000'))->toBe('City of Isabela, City of Isabela (Not a Province), Zamboanga Peninsula')
        ->and($index->label('1908703000'))->toBe(
            'City of Cotabato, Maguindanao del Norte, Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)'
        )
        ->and($index->label('0301401001'))->toBe('Banaban, Angat, Bulacan, Central Luzon');
});

it('returns null labels for missing or malformed codes', function () {
    $index = psgcTestIndex();

    expect($index->label(null))->toBeNull()
        ->and($index->label(''))->toBeNull()
        ->and($index->label('1234'))->toBeNull()
        ->and($index->label('9999999999'))->toBeNull();
});

it('reduces official region names to their inner name only when they match the Region X (Name) pattern', function (string $region, string $expected) {
    expect(PsgcIndex::regionLabel($region))->toBe($expected);
})->with([
    'roman numeral region' => ['Region III (Central Luzon)', 'Central Luzon'],
    'letter-suffixed region' => ['Region IV-A (CALABARZON)', 'CALABARZON'],
    'two-word inner name' => ['Region I (Ilocos Region)', 'Ilocos Region'],
    'roman numeral thirteen' => ['Region XIII (Caraga)', 'Caraga'],
    'non-conforming NCR name' => ['National Capital Region (NCR)', 'National Capital Region (NCR)'],
    'non-conforming MIMAROPA name' => ['MIMAROPA Region', 'MIMAROPA Region'],
    'non-conforming BARMM name' => [
        'Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)',
        'Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)',
    ],
]);

it('normalizes free text by trimming, collapsing whitespace, lowercasing and folding accents', function () {
    expect(PsgcIndex::normalize('  Parañaque  '))->toBe('paranaque')
        ->and(PsgcIndex::normalize("ILOCOS\tRegion"))->toBe('ilocos region')
        ->and(PsgcIndex::normalize('CITY OF   ISABELA'))->toBe('city of isabela')
        ->and(PsgcIndex::normalize(''))->toBe('')
        ->and(PsgcIndex::normalize("\xB1\x31"))->toBe('');
});

it('finds a node by its 10-digit code with the full suggestion shape', function () {
    $index = psgcTestIndex();

    $row = $index->findByCode('0301420061');

    expect($row)->toBe([
        'code' => '0301420061',
        'name' => 'Muzon South',
        'level' => 'Bgy',
        'parent' => '0301420000',
        'label' => 'Muzon South, City of San Jose Del Monte, Bulacan, Central Luzon',
    ]);
});

it('returns null for malformed or unknown codes', function () {
    $index = psgcTestIndex();

    expect($index->findByCode(null))->toBeNull()
        ->and($index->findByCode(''))->toBeNull()
        ->and($index->findByCode('1234'))->toBeNull()
        ->and($index->findByCode('abcdefghij'))->toBeNull()
        ->and($index->findByCode('9999999999'))->toBeNull();
});

it('never leaks the internal normalized name into suggestion rows', function () {
    $index = psgcTestIndex();

    foreach ($index->search('muzon', 5) as $row) {
        expect(array_keys($row))->toBe(['code', 'name', 'level', 'parent', 'label'])
            ->and(is_string($row['code']))->toBeTrue()
            ->and(is_string($row['name']))->toBeTrue()
            ->and(is_string($row['level']))->toBeTrue()
            ->and($row['parent'] === null || is_string($row['parent']))->toBeTrue()
            ->and(is_string($row['label']))->toBeTrue();
    }
});

it('returns the ancestor chain from region to leaf', function () {
    $index = psgcTestIndex();

    $chain = $index->hierarchy('0301420061');

    expect(array_column($chain, 'name'))->toBe([
        'Region III (Central Luzon)',
        'Bulacan',
        'City of San Jose Del Monte',
        'Muzon South',
    ])->and(array_column($chain, 'level'))->toBe(['Reg', 'Prov', 'City', 'Bgy']);
});

it('returns an empty chain for malformed or unknown codes', function () {
    $index = psgcTestIndex();

    expect($index->hierarchy('0000000000'))->toBe([])
        ->and($index->hierarchy('nope'))->toBe([]);
});

it('returns no suggestions for empty, too-short or invalid UTF-8 queries', function () {
    $index = psgcTestIndex();

    expect($index->search(''))->toBe([])
        ->and($index->search('s'))->toBe([])
        ->and($index->search('   '))->toBe([])
        ->and($index->search("\xB1\x31"))->toBe([]);
});

it('exposes a minimum query length of two characters', function () {
    expect(PsgcIndex::MIN_QUERY_LENGTH)->toBe(2);
});

it('ranks exact name matches before longer variants and stays deterministic', function () {
    $index = psgcTestIndex();
    $rows = $index->search('muzon');

    expect(array_column($rows, 'code'))->toBe([
        '0401023024',
        '0401024016',
        '0402115024',
        '0405813002',
        '1380400012',
        '0402117007',
        '0402117015',
        '0301420060',
        '0301420062',
        '0301420061',
        '0301420007',
        '0401002011',
    ]);

    foreach (array_slice($rows, 0, 5) as $row) {
        expect($row['name'])->toBe('Muzon');
    }
    foreach (array_slice($rows, 5) as $row) {
        expect($row['name'])->toStartWith('Muzon')
            ->and($row['name'])->not->toBe('Muzon');
    }

    // Repeating the query yields the identical list.
    expect(array_column($index->search('muzon'), 'code'))
        ->toBe(array_column($rows, 'code'));
});

it('matches case-insensitively, accent-insensitively and across extra whitespace', function () {
    $index = psgcTestIndex();

    $lower = $index->search('bulacan');
    $upper = $index->search('BULACAN');
    $padded = $index->search('  SAN   JUAN  ');

    expect($lower)->not->toBe([])
        ->and(array_column($upper, 'code'))->toBe(array_column($lower, 'code'))
        ->and($upper[0]['name'])->toBe('Bulacan')
        ->and($index->search('paranaque')[0]['name'])->toBe('City of Parañaque')
        ->and($index->search('Parañaque')[0]['name'])->toBe('City of Parañaque')
        ->and($padded[0]['name'])->toBe('San Juan');
});

it('matches substrings when no prefix match exists and orders them by length', function () {
    $index = psgcTestIndex();
    $rows = $index->search('uzon');

    expect($rows)->not->toBe([]);
    expect(array_column(array_slice($rows, 0, 6), 'code'))->toBe([
        '0105543007',
        '0201511014',
        '0203107012',
        '1102506005',
        '0401023024',
        '0401024016',
    ]);
    expect($rows[0]['name'])->toBe('Guzon');

    foreach ($rows as $row) {
        expect($row['name'])->toContain('uzon')
            ->and($row['name'])->not->toStartWith('uzon');
    }
});

it('breaks duplicate-name ties deterministically by ascending PSGC code', function () {
    $index = psgcTestIndex();

    expect(array_column($index->search('poblacion', 3), 'code'))->toBe([
        '0102806011',
        '0102814009',
        '0102902015',
    ]);
});

it('honors the caller limit and clamps pathological limits to the maximum', function () {
    $index = psgcTestIndex();

    expect($index->search('muzon', 0))->toBe([])
        ->and($index->search('muzon', -5))->toBe([])
        ->and($index->search('muzon', 3))->toHaveCount(3)
        ->and($index->search('muzon'))->toHaveCount(12)
        ->and($index->search('poblacion', 100000))->toHaveCount(PsgcIndex::MAX_LIMIT);
});

it('rebuilds a fresh index after the static memo is flushed', function () {
    PsgcIndex::flush();
    $first = psgcTestIndex();
    PsgcIndex::flush();
    $second = psgcTestIndex();

    expect($first->search('bulacan', 1))->toBe($second->search('bulacan', 1))
        ->and($second->label('0301400000'))->toBe('Bulacan, Central Luzon');
});
