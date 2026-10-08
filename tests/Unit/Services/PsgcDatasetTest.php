<?php

use App\Services\PsgcIndex;

/**
 * Integrity tests for the generated PSGC dataset and its manifest.
 *
 * These tests pin the official PSA "as of 30 June 2026" release so any
 * accidental regeneration from modified sources, partial write, or schema
 * drift fails loudly before it can reach patient-facing code.
 */

function psgcDatasetFile(): string
{
    return dirname(__DIR__, 3) . '/resources/data/psgc/psgc.json';
}

function psgcManifestFile(): string
{
    return dirname(__DIR__, 3) . '/resources/data/psgc/manifest.json';
}

function psgcDataset(): array
{
    static $data = null;

    if ($data === null) {
        $raw = file_get_contents(psgcDatasetFile());
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    return $data;
}

it('contains the official PSA 2Q 2026 publication node count', function () {
    $dataset = psgcDataset();

    expect($dataset)->toHaveKey('nodes')
        ->and($dataset['nodes'])->toHaveCount(43768);
});

it('pins the official geographic level counts', function () {
    $counts = [];
    foreach (psgcDataset()['nodes'] as $node) {
        $key = $node['level'] === '' ? 'special' : $node['level'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts);

    expect($counts)->toBe([
        'Bgy' => 42010,
        'City' => 149,
        'Mun' => 1493,
        'Prov' => 82,
        'Reg' => 18,
        'SubMun' => 14,
        'special' => 2,
    ]);
});

it('keeps every code unique, well-formed and sorted ascending', function () {
    $codes = [];
    foreach (psgcDataset()['nodes'] as $node) {
        expect($node['code'])->toMatch('/^\d{10}$/');
        $codes[] = $node['code'];
    }

    $sorted = $codes;
    sort($sorted, SORT_STRING);

    expect($codes)->toBe($sorted)
        ->and(array_unique($codes))->toHaveCount(43768);
});

it('stores only the four published fields with clean official names', function () {
    foreach (psgcDataset()['nodes'] as $node) {
        expect(array_keys($node))->toBe(['code', 'name', 'level', 'parent'])
            ->and(mb_check_encoding($node['name'], 'UTF-8'))->toBeTrue()
            ->and($node['name'])->toBe(trim($node['name']))
            ->and($node['name'])->not->toBe('');
    }
});

it('derives parents that always exist, never self-reference and respect levels', function () {
    $nodes = [];
    foreach (psgcDataset()['nodes'] as $node) {
        $nodes[$node['code']] = $node;
    }

    $allowed = [
        'Reg' => [null],
        'Prov' => ['Reg'],
        'City' => ['Prov', 'Reg', ''],
        'Mun' => ['Prov', 'Reg', ''],
        'SubMun' => ['City'],
        'Bgy' => ['City', 'Mun', 'SubMun'],
        '' => ['Reg'],
    ];

    $maxChain = 0;

    foreach ($nodes as $code => $node) {
        if ($node['level'] === 'Reg') {
            expect($node['parent'])->toBeNull();
            continue;
        }

        expect($node['parent'])->not->toBeNull()
            ->and($node['parent'])->not->toBe((string) $code)
            ->and($nodes)->toHaveKey((string) $node['parent']);

        $parentLevel = $nodes[$node['parent']]['level'];
        expect($allowed[$node['level']])->toContain($parentLevel);

        $chain = 0;
        $cursor = $node['parent'];
        while ($cursor !== null && isset($nodes[$cursor])) {
            $chain++;
            $cursor = $nodes[$cursor]['parent'];
        }
        $maxChain = max($maxChain, $chain);
    }

    // Region > province > city > sub-municipality > barangay is the deepest
    // possible official chain (Manila, Isabela City container case).
    expect($maxChain)->toBeLessThanOrEqual(4);
});

it('links known hierarchy fixtures to their official parents', function () {
    $nodes = [];
    foreach (psgcDataset()['nodes'] as $node) {
        $nodes[$node['code']] = $node;
    }

    $fixtures = [
        '0301400000' => ['Bulacan', 'Prov', '0300000000'],
        '1400100000' => ['Abra', 'Prov', '1400000000'],
        '1380100000' => ['City of Caloocan', 'City', '1300000000'],
        '1381701000' => ['Pateros', 'Mun', '1300000000'],
        '0730600000' => ['City of Cebu', 'City', '0700000000'],
        '1380601000' => ['Tondo I/II', 'SubMun', '1380600000'],
        '0301420061' => ['Muzon South', 'Bgy', '0301420000'],
        '0990101000' => ['City of Isabela', 'City', '0990100000'],
        '0990100000' => ['City of Isabela (Not a Province)', '', '0900000000'],
        '1999900000' => ['Special Geographic Area', '', '1900000000'],
        '1999901000' => ['Kapalawan', 'Mun', '1999900000'],
        '1908703000' => ['City of Cotabato', 'City', '1908700000'],
    ];

    foreach ($fixtures as $code => [$name, $level, $parent]) {
        expect($nodes[$code]['name'])->toBe($name)
            ->and($nodes[$code]['level'])->toBe($level)
            ->and((string) $nodes[$code]['parent'])->toBe($parent);
    }
});

it('keeps NCR composed of its sixteen cities and Pateros', function () {
    $children = ['City' => 0, 'Mun' => 0, 'Prov' => 0];
    foreach (psgcDataset()['nodes'] as $node) {
        if ((string) $node['parent'] === '1300000000') {
            $children[$node['level']] = ($children[$node['level']] ?? 0) + 1;
        }
    }

    expect($children['City'])->toBe(16)
        ->and($children['Mun'])->toBe(1)
        ->and($children['Prov'])->toBe(0);
});

it('keeps all fourteen Manila sub-municipalities under the City of Manila', function () {
    $subMuns = array_filter(
        psgcDataset()['nodes'],
        static fn (array $node): bool => $node['level'] === 'SubMun'
    );

    expect($subMuns)->toHaveCount(14);
    foreach ($subMuns as $node) {
        expect((string) $node['parent'])->toBe('1380600000');
    }
});

it('preserves duplicate official names instead of de-duplicating them', function () {
    $poblacion = 0;
    $muzon = 0;
    foreach (psgcDataset()['nodes'] as $node) {
        if ($node['name'] === 'Poblacion') {
            $poblacion++;
        }
        if ($node['name'] === 'Muzon') {
            $muzon++;
        }
    }

    expect($poblacion)->toBe(607)
        ->and($muzon)->toBe(5);
});

it('records a manifest that matches the dataset byte-for-byte', function () {
    $manifest = json_decode(file_get_contents(psgcManifestFile()), true, 512, JSON_THROW_ON_ERROR);
    $datasetSha = hash_file('sha256', psgcDatasetFile());

    $pinnedSha = '66fcd40fe10249f57efe604c09f77c56173ea7d1fc80b446b5bcca914b31a2b3';

    expect($manifest['schemaVersion'])->toBe('1.0.0')
        ->and($manifest['release']['publicationDate'])->toBe('2026-06-30')
        ->and($manifest['release']['psgcRelease'])->toBe('PSGC as of 2026-06-30')
        ->and($manifest['originator'])->toContain('Philippine Statistics Authority')
        ->and($manifest['dataset']['sha256'])->toBe($pinnedSha)
        ->and($datasetSha)->toBe($pinnedSha)
        ->and($manifest['dataset']['recordCount'])->toBe(43768)
        ->and($manifest['counts']['total'])->toBe(43768)
        ->and($manifest['validation']['summaryCrossChecks'])->toBe('passed')
        ->and($manifest['validation']['officialTotals'])->toBe('passed')
        ->and($manifest['hierarchy']['maxDepth'])->toBe(4)
        ->and($manifest['sources'])->toHaveCount(2);
});

it('keeps the official source workbooks present and unchanged in size', function () {
    $sourcesDir = dirname(__DIR__, 3) . '/resources/data/psgc/source';

    $datafile = $sourcesDir . '/PSGC-2Q-2026-Publication-Datafile.xlsx';
    $summary = $sourcesDir . '/PSGC-2Q-2026-National-and-Provincial-Summary.xlsx';

    expect(is_file($datafile))->toBeTrue()
        ->and(is_file($summary))->toBeTrue()
        ->and(filesize($datafile))->toBe(3250889)
        ->and(filesize($summary))->toBe(87973);

    // ZIP magic bytes: confirms these are genuine xlsx containers.
    expect(substr(file_get_contents($datafile, false, null, 0, 2), 0, 2))->toBe('PK')
        ->and(substr(file_get_contents($summary, false, null, 0, 2), 0, 2))->toBe('PK');
});

it('rebuilds an index over the dataset without touching the database', function () {
    PsgcIndex::flush();
    $index = new PsgcIndex(psgcDatasetFile());

    expect($index->findByCode('0301420061')['name'])->toBe('Muzon South');
});
