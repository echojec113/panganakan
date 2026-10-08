<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Converts the official PSA PSGC Excel publication (locally downloaded,
 * stored under resources/data/psgc/source/) into the compact local dataset
 * resources/data/psgc/psgc.json plus its manifest.json.
 *
 * Design notes (Phase 1, DEPLA address autocomplete):
 *   - Pure file I/O: never opens a database connection and never writes
 *     anything except the two output files in --out.
 *   - Uses only PHP core extensions (zip, xmlreader, SimpleXML, mbstring),
 *     so no new Composer dependency is required.
 *   - Parent relationships are derived with the official PSGC coding
 *     structure (RR-PPP-MM-BBB) and are then cross-validated against the
 *     PSA National Summary and Provincial Summary sheets, which are the
 *     authoritative listing of which cities/municipalities belong to which
 *     province or region. Any mismatch aborts the run before output is
 *     written, so a broken hierarchy can never be published silently.
 *   - Deterministic output: psgc.json nodes are sorted by PSGC code and the
 *     file contains no timestamps, so re-running on the same source yields
 *     a byte-identical dataset.
 */
class PsgcConvert extends Command
{
    public const DEFAULT_SOURCE_DIR = 'data/psgc/source';

    public const DEFAULT_OUT_DIR = 'data/psgc';

    public const DATASET_FILENAME = 'psgc.json';

    public const MANIFEST_FILENAME = 'manifest.json';

    public const SCHEMA_VERSION = '1.0.0';

    public const SOURCE_URL = 'https://psa.gov.ph/classification/psgc/';

    /** Geographic levels published in the PSGC sheet, plus '' for the two structural rows. */
    private const KNOWN_LEVELS = ['Reg', 'Prov', 'City', 'Mun', 'SubMun', 'Bgy', ''];

    protected $signature = 'psgc:convert
        {--datafile= : Absolute path to the PSGC Publication Datafile xlsx (default: single *Publication-Datafile.xlsx in the source dir)}
        {--summary= : Absolute path to the National and Provincial Summary xlsx (default: single *National-and-Provincial-Summary.xlsx in the source dir)}
        {--out= : Output directory for psgc.json and manifest.json (default: resources/data/psgc)}';

    protected $description = 'Convert the official local PSA PSGC Excel publication into psgc.json and manifest.json (no database access)';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $sourceDir = self::DEFAULT_SOURCE_DIR;
        $outDir = $this->option('out') ?: resource_path(self::DEFAULT_OUT_DIR);

        try {
            $datafile = $this->resolveSourceFile(
                $this->option('datafile'),
                resource_path($sourceDir),
                '*Publication-Datafile.xlsx',
                'PSG C publication datafile'
            );
            $summary = $this->resolveSourceFile(
                $this->option('summary'),
                resource_path($sourceDir),
                '*National-and-Provincial-Summary.xlsx',
                'PSG C national and provincial summary'
            );

            $this->info('Datafile : ' . $datafile);
            $this->info('Summary  : ' . $summary);
            $this->info('Output   : ' . $outDir);

            $publicationDate = $this->readPublicationDate($datafile);
            $this->info('Publication date (Metadata sheet): ' . $publicationDate['verbatim']);

            $records = $this->readPsgcSheet($datafile);
            $this->info(sprintf('Parsed %d PSGC rows.', count($records['nodes'])));

            $nodes = $this->deriveParents($records);

            $summaryRows = $this->readSummarySheet($summary);
            $nationalFile1 = $this->readNationalSummarySheet($datafile);

            $mismatches = array_merge(
                $this->crossValidateSummary($nodes, $summaryRows, $nationalFile1),
                $this->checkOfficialTotals($nodes)
            );

            if ($mismatches !== []) {
                $this->error('Validation failed. No files were written.');
                foreach (array_slice($mismatches, 0, 30) as $line) {
                    $this->error('  - ' . $line);
                }
                if (count($mismatches) > 30) {
                    $this->error('  ...and ' . (count($mismatches) - 30) . ' more.');
                }

                return self::FAILURE;
            }

            $counts = $this->countByLevel($nodes);
            $trimmedNames = $records['trimmedNames'];
            unset($records);

            $written = $this->writeOutputs($outDir, $nodes, [
                'publication' => $publicationDate,
                'datafile' => $datafile,
                'summary' => $summary,
                'counts' => $counts,
                'trimmedNames' => $trimmedNames,
            ]);
            unset($nodes);

            $this->newLine();
            $this->info('Validation passed (parent links, National + Provincial Summary cross-checks).');
            foreach ($counts as $level => $count) {
                $this->line(sprintf('  %-7s %7d', $level === '' ? '(spec)' : $level, $count));
            }
            $this->info('Wrote ' . $written['datasetPath'] . ' (' . number_format($written['datasetBytes']) . ' bytes, sha256 ' . substr($written['datasetSha256'], 0, 16) . '...)');
            $this->info('Wrote ' . $written['manifestPath']);

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveSourceFile(?string $explicit, string $sourceDir, string $pattern, string $label): string
    {
        if ($explicit !== null && $explicit !== '') {
            $path = $explicit;
        } else {
            $matches = glob(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $pattern) ?: [];
            if (count($matches) !== 1) {
                throw new RuntimeException(sprintf(
                    'Expected exactly one %s matching "%s" in %s, found %d. Pass --datafile/--summary explicitly.',
                    $label,
                    $pattern,
                    $sourceDir,
                    count($matches)
                ));
            }
            $path = $matches[0];
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Source file not readable: {$path}");
        }

        $fh = fopen($path, 'rb');
        $magic = $fh !== false ? fread($fh, 4) : '';
        if ($fh !== false) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04") {
            throw new RuntimeException("Source file is not a valid xlsx (zip) file: {$path}");
        }

        return $path;
    }

    /**
     * @return array{verbatim: string, iso: string}
     */
    private function readPublicationDate(string $xlsx): array
    {
        $xml = $this->zipEntry($xlsx, 'xl/worksheets/sheet1.xml');
        $shared = $this->sharedStrings($xlsx);

        foreach ($this->rowGenerator($xml, $shared) as $cells) {
            $label = trim((string)($cells['A'] ?? ''));
            if (strcasecmp($label, 'Publication date:') === 0) {
                $verbatim = trim((string)($cells['B'] ?? ''));
                $ts = strtotime($verbatim);
                if ($verbatim === '' || $ts === false) {
                    throw new RuntimeException("Cannot parse Metadata publication date: '{$verbatim}'");
                }

                return ['verbatim' => $verbatim, 'iso' => date('Y-m-d', $ts)];
            }
        }

        throw new RuntimeException('Metadata sheet does not contain a "Publication date:" row.');
    }

    /**
     * Reads the PSGC master sheet and validates codes, names, levels and duplicates.
     *
     * @return array{nodes: array<int, array{code: string, name: string, level: string}>, trimmedNames: int}
     */
    private function readPsgcSheet(string $xlsx): array
    {
        $xml = $this->zipEntry($xlsx, 'xl/worksheets/sheet4.xml');
        $shared = $this->sharedStrings($xlsx);

        $seen = [];
        $errors = [];
        $rows = [];
        $trimmedNames = 0;
        $headerSeen = false;

        foreach ($this->rowGenerator($xml, $shared) as $cells) {
            $code = trim((string)($cells['A'] ?? ''));
            if (!$headerSeen) {
                if ($code !== '10-digit PSGC') {
                    throw new RuntimeException('Unexpected PSGC sheet layout: first cell is not "10-digit PSGC".');
                }
                $headerSeen = true;
                continue;
            }

            $rawName = (string)($cells['B'] ?? '');
            $name = trim($rawName);
            $level = trim((string)($cells['D'] ?? ''));

            if ($code === '' && $name === '') {
                continue;
            }

            if (!preg_match('/^\d{10}$/', $code)) {
                $errors[] = "invalid 10-digit code '{$code}' (name '{$name}')";
                continue;
            }
            if (isset($seen[$code])) {
                $errors[] = "duplicate code {$code} ({$name})";
                continue;
            }
            if ($name === '') {
                $errors[] = "empty name for code {$code}";
                continue;
            }
            if (!in_array($level, self::KNOWN_LEVELS, true)) {
                $errors[] = "unknown geographic level '{$level}' for {$code} ({$name})";
                continue;
            }

            if ($name !== $rawName) {
                $trimmedNames++;
            }

            $seen[$code] = true;
            $rows[] = ['code' => $code, 'name' => $name, 'level' => $level];
        }

        if (!$headerSeen) {
            throw new RuntimeException('PSGC sheet is empty.');
        }
        if ($errors !== []) {
            throw new RuntimeException(
                "Record validation failed:\n  - " . implode("\n  - ", array_slice($errors, 0, 30))
                . (count($errors) > 30 ? "\n  ...and " . (count($errors) - 30) . ' more' : '')
            );
        }

        return ['nodes' => $rows, 'trimmedNames' => $trimmedNames];
    }

    /**
     * Derives official parent links for every record using the PSGC coding
     * structure, then validates level compatibility, existence and cycles.
     *
     * @param array{nodes: array<int, array{code: string, name: string, level: string}>, trimmedNames: int} $records
     *
     * @return array<string, array{code: string, name: string, level: string, parent: ?string}>
     */
    private function deriveParents(array $records): array
    {
        $nodes = [];
        foreach ($records['nodes'] as $row) {
            $nodes[$row['code']] = ['code' => $row['code'], 'name' => $row['name'], 'level' => $row['level'], 'parent' => null];
        }

        $errors = [];

        foreach ($nodes as $code => $node) {
            $regionCode = substr($code, 0, 2) . '00000000';

            switch ($node['level']) {
                case 'Reg':
                    $parent = null;
                    break;

                case 'Prov':
                    $parent = $regionCode;
                    break;

                case 'Bgy':
                    $parent = substr($code, 0, 7) . '000';
                    break;

                case 'City':
                case 'Mun':
                case 'SubMun':
                case '':
                    $cand = substr($code, 0, 5) . '00000';
                    $parent = (isset($nodes[$cand]) && $cand !== (string)$code) ? $cand : $regionCode;
                    break;

                default:
                    $parent = null;
            }

            if ($parent !== null && !isset($nodes[$parent])) {
                $errors[] = "missing parent {$parent} for {$code} ({$node['name']}, {$node['level']})";
                continue;
            }

            $nodes[$code]['parent'] = $parent;
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

        foreach ($nodes as $code => $node) {
            $parentLevel = $node['parent'] === null ? null : $nodes[$node['parent']]['level'];
            if (!in_array($parentLevel, $allowed[$node['level']], true)) {
                $errors[] = sprintf(
                    '%s (%s, %s) has parent level %s (%s), expected one of [%s]',
                    $code,
                    $node['name'],
                    $node['level'],
                    $parentLevel === null ? 'none' : "'{$parentLevel}'",
                    $node['parent'] ?? '-',
                    implode(', ', array_map(static fn ($l) => $l === null ? 'none' : "'{$l}'", $allowed[$node['level']]))
                );
            }
        }

        foreach ($nodes as $code => $node) {
            $depth = 0;
            $cursor = $code;
            while ($nodes[$cursor]['parent'] !== null) {
                $cursor = $nodes[$cursor]['parent'];
                if (++$depth > 8) {
                    $errors[] = "parent cycle detected at {$code}";
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "Hierarchy validation failed:\n  - " . implode("\n  - ", array_slice($errors, 0, 30))
                . (count($errors) > 30 ? "\n  ...and " . (count($errors) - 30) . ' more' : '')
            );
        }

        return $nodes;
    }

    /**
     * Parses the Provincial Summary sheet (file 2), preserving row order so
     * region/province/independent-LGU grouping is available.
     *
     * @return array<int, array{code: ?string, name: string, prov: int, city: int, mun: int, bgy: int}>
     */
    private function readSummarySheet(string $xlsx): array
    {
        $xml = $this->zipEntry($xlsx, 'xl/worksheets/sheet1.xml');
        $shared = $this->sharedStrings($xlsx);

        $rows = [];
        foreach ($this->rowGenerator($xml, $shared) as $cells) {
            $code = trim((string)($cells['A'] ?? ''));
            $name = trim((string)($cells['B'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'code' => preg_match('/^\d{10}$/', $code) === 1 ? $code : null,
                'name' => $name,
                'prov' => (int)($cells['C'] ?? 0),
                'city' => (int)($cells['D'] ?? 0),
                'mun' => (int)($cells['E'] ?? 0),
                'bgy' => (int)($cells['F'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Parses the National Summary sheet inside the Publication Datafile (file 1)
     * for a second independent set of per-region counts.
     *
     * @return array<string, array{prov: int, city: int, mun: int, bgy: int}>
     */
    private function readNationalSummarySheet(string $xlsx): array
    {
        $xml = $this->zipEntry($xlsx, 'xl/worksheets/sheet2.xml');
        $shared = $this->sharedStrings($xlsx);

        $rows = [];
        foreach ($this->rowGenerator($xml, $shared) as $cells) {
            $code = trim((string)($cells['A'] ?? ''));
            if (preg_match('/^\d{10}$/', $code) !== 1) {
                continue;
            }
            $rows[$code] = [
                'prov' => (int)($cells['D'] ?? -1),
                'city' => (int)($cells['E'] ?? -1),
                'mun' => (int)($cells['F'] ?? -1),
                'bgy' => (int)($cells['G'] ?? -1),
            ];
        }

        return $rows;
    }

    /**
     * Cross-checks the derived hierarchy against both official summary sheets.
     *
     * @return array<int, string> list of mismatch descriptions (empty = pass)
     */
    private function crossValidateSummary(array $nodes, array $summaryRows, array $nationalFile1): array
    {
        $mismatches = [];

        $regCodes = [];
        $provCodes = [];
        foreach ($nodes as $code => $node) {
            if ($node['level'] === 'Reg') {
                $regCodes[$code] = true;
            }
            if ($node['level'] === 'Prov') {
                $provCodes[$code] = true;
            }
        }

        $regionOf = [];
        $bgyUnder = [];
        $children = [];
        foreach ($nodes as $code => $node) {
            if ($node['parent'] !== null) {
                $children[$node['parent']][] = $code;
            }
        }
        foreach ($nodes as $code => $node) {
            $chain = [];
            $cursor = $code;
            while ($nodes[$cursor]['parent'] !== null) {
                $cursor = $nodes[$cursor]['parent'];
                $chain[] = $cursor;
                if (count($chain) > 8) {
                    break;
                }
            }
            $regionOf[$code] = end($chain) ?: null;
            if ($node['level'] === 'Bgy') {
                foreach ($chain as $ancestor) {
                    $bgyUnder[$ancestor] = ($bgyUnder[$ancestor] ?? 0) + 1;
                }
            }
        }

        $directCount = static function (array $nodes, array $children, string $parent, string $level): int {
            $n = 0;
            foreach ($children[$parent] ?? [] as $child) {
                if ($nodes[$child]['level'] === $level) {
                    $n++;
                }
            }

            return $n;
        };

        $currentRegion = null;
        $summaryRegionCodes = [];
        $summaryProvCodes = [];
        $national = null;

        foreach ($summaryRows as $row) {
            if ($row['name'] === 'PHILIPPINES' && $row['code'] === null) {
                $national = $row;
                continue;
            }
            if ($row['code'] === null) {
                continue;
            }
            $code = $row['code'];

            if (isset($regCodes[$code])) {
                $currentRegion = $code;
                $summaryRegionCodes[$code] = true;

                $provChildren = $directCount($nodes, $children, $code, 'Prov');
                $cityRoot = 0;
                $munRoot = 0;
                foreach ($nodes as $n) {
                    if ($regionOf[$n['code']] !== $code) {
                        continue;
                    }
                    if ($n['level'] === 'City') {
                        $cityRoot++;
                    }
                    if ($n['level'] === 'Mun') {
                        $munRoot++;
                    }
                }
                if ($provChildren !== $row['prov']) {
                    $mismatches[] = "region {$code} province children: derived {$provChildren} vs summary {$row['prov']}";
                }
                if ($cityRoot !== $row['city']) {
                    $mismatches[] = "region {$code} city total: derived {$cityRoot} vs summary {$row['city']}";
                }
                if ($munRoot !== $row['mun']) {
                    $mismatches[] = "region {$code} mun total: derived {$munRoot} vs summary {$row['mun']}";
                }
                if (($bgyUnder[$code] ?? 0) !== $row['bgy']) {
                    $mismatches[] = "region {$code} bgy total: derived " . ($bgyUnder[$code] ?? 0) . " vs summary {$row['bgy']}";
                }

                $f1 = $nationalFile1[$code] ?? null;
                if ($f1 === null) {
                    $mismatches[] = "region {$code} missing from Publication Datafile National Summary sheet";
                } else {
                    if ($f1['prov'] !== $row['prov'] || $f1['city'] !== $row['city'] || $f1['mun'] !== $row['mun'] || $f1['bgy'] !== $row['bgy']) {
                        $mismatches[] = "region {$code} counts differ between National Summary (file 1) and Provincial Summary (file 2)";
                    }
                }

                continue;
            }

            if (isset($provCodes[$code])) {
                $summaryProvCodes[$code] = true;
                if ($currentRegion === null || $regionOf[$code] !== $currentRegion) {
                    $mismatches[] = "province {$code} ({$row['name']}) not under region " . ($currentRegion ?? '(none)');
                }
                if ($row['prov'] !== 1) {
                    $mismatches[] = "province {$code} row must have C=1, has {$row['prov']}";
                }
                $city = $directCount($nodes, $children, $code, 'City');
                $mun = $directCount($nodes, $children, $code, 'Mun');
                if ($city !== $row['city']) {
                    $mismatches[] = "province {$code} city children: derived {$city} vs summary {$row['city']}";
                }
                if ($mun !== $row['mun']) {
                    $mismatches[] = "province {$code} mun children: derived {$mun} vs summary {$row['mun']}";
                }
                if (($bgyUnder[$code] ?? 0) !== $row['bgy']) {
                    $mismatches[] = "province {$code} bgy total: derived " . ($bgyUnder[$code] ?? 0) . " vs summary {$row['bgy']}";
                }

                continue;
            }

            if (!isset($nodes[$code])) {
                $mismatches[] = "summary row {$code} ({$row['name']}) does not exist in the PSGC masterlist";
                continue;
            }

            $node = $nodes[$code];
            if ($currentRegion === null) {
                $mismatches[] = "independent row {$code} appears before any region heading";
                continue;
            }
            if (($regionOf[$code] ?? null) !== $currentRegion) {
                $mismatches[] = "independent row {$code} ({$node['name']}) resolves to region " . ($regionOf[$code] ?? 'none') . ", summary places it under {$currentRegion}";
            }

            $directParent = $node['parent'];
            $parentIsSpecial = $directParent !== null
                && $nodes[$directParent]['level'] === ''
                && $nodes[$directParent]['parent'] === $currentRegion;
            if ($directParent !== $currentRegion && !$parentIsSpecial) {
                $mismatches[] = "independent row {$code} ({$node['name']}) direct parent is " . ($directParent ?? 'none') . ", expected region {$currentRegion} (or its special container)";
            }

            if ($row['prov'] !== 0) {
                $mismatches[] = "independent row {$code} summary C (PROV) flag is {$row['prov']}, expected 0";
            }
            if ($node['level'] === 'City' && $row['city'] !== 1) {
                $mismatches[] = "independent city {$code} summary D flag is {$row['city']}, expected 1";
            }
            if ($node['level'] === 'Mun' && ($row['mun'] !== 1 || $row['city'] !== 0)) {
                $mismatches[] = "independent municipality {$code} summary flags are D={$row['city']} E={$row['mun']}, expected D=0 E=1";
            }
            if ($node['level'] === '' && $row['city'] !== 0) {
                $mismatches[] = "special container {$code} summary D flag is {$row['city']}, expected 0";
            }
            if ($node['level'] === 'Prov') {
                $mismatches[] = "province {$code} re-appeared as an independent row";
            }

            if ($node['level'] === '') {
                $munChildren = $directCount($nodes, $children, $code, 'Mun');
                if ($munChildren !== $row['mun']) {
                    $mismatches[] = "special container {$code} mun children: derived {$munChildren} vs summary {$row['mun']}";
                }
            }

            if (($bgyUnder[$code] ?? 0) !== $row['bgy']) {
                $mismatches[] = "row {$code} ({$node['name']}) bgy total: derived " . ($bgyUnder[$code] ?? 0) . " vs summary {$row['bgy']}";
            }
        }

        if ($national === null) {
            $mismatches[] = 'Provincial Summary is missing the PHILIPPINES total row';
        }

        $derivedProv = count($provCodes);
        $derivedCity = 0;
        $derivedMun = 0;
        $derivedBgy = 0;
        $derivedSubMun = 0;
        foreach ($nodes as $node) {
            if ($node['level'] === 'City') {
                $derivedCity++;
            }
            if ($node['level'] === 'Mun') {
                $derivedMun++;
            }
            if ($node['level'] === 'Bgy') {
                $derivedBgy++;
            }
            if ($node['level'] === 'SubMun') {
                $derivedSubMun++;
            }
        }

        if ($national !== null) {
            if ($national['prov'] !== $derivedProv) {
                $mismatches[] = "national provinces: derived {$derivedProv} vs summary {$national['prov']}";
            }
            if ($national['city'] !== $derivedCity) {
                $mismatches[] = "national cities: derived {$derivedCity} vs summary {$national['city']}";
            }
            if ($national['mun'] !== $derivedMun) {
                $mismatches[] = "national municipalities: derived {$derivedMun} vs summary {$national['mun']}";
            }
            if ($national['bgy'] !== $derivedBgy) {
                $mismatches[] = "national barangays: derived {$derivedBgy} vs summary {$national['bgy']}";
            }
        }

        foreach ($regCodes as $code => $_) {
            if (!isset($summaryRegionCodes[$code])) {
                $mismatches[] = "region {$code} missing from Provincial Summary";
            }
        }
        $missingProvinces = array_diff(array_keys($provCodes), array_keys($summaryProvCodes));
        foreach ($missingProvinces as $code) {
            $mismatches[] = "province {$code} missing from Provincial Summary";
        }

        return $mismatches;
    }

    /**
     * @return array<int, string>
     */
    private function checkOfficialTotals(array $nodes): array
    {
        $mismatches = [];

        $expected = [
            'Reg' => 18,
            'Prov' => 82,
            'City' => 149,
            'Mun' => 1493,
            'SubMun' => 14,
            'Bgy' => 42010,
            '' => 2,
        ];

        $counts = $this->countByLevel($nodes);
        foreach ($expected as $level => $count) {
            if (($counts[$level] ?? 0) !== $count) {
                $mismatches[] = sprintf(
                    "official %s count for the 30 June 2026 release is %d, dataset has %d",
                    $level === '' ? 'structural rows' : $level,
                    $count,
                    $counts[$level] ?? 0
                );
            }
        }

        return $mismatches;
    }

    private function countByLevel(array $nodes): array
    {
        $counts = [];
        foreach ($nodes as $node) {
            $counts[$node['level']] = ($counts[$node['level']] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    private function writeOutputs(string $outDir, array $nodes, array $context): array
    {
        if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
            throw new RuntimeException("Cannot create output directory: {$outDir}");
        }

        uasort($nodes, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));

        $payload = ['nodes' => []];
        foreach ($nodes as $node) {
            $payload['nodes'][] = [
                'code' => $node['code'],
                'name' => $node['name'],
                'level' => $node['level'],
                'parent' => $node['parent'],
            ];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        $json .= "\n";

        $datasetPath = $outDir . DIRECTORY_SEPARATOR . self::DATASET_FILENAME;
        if (file_put_contents($datasetPath, $json, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$datasetPath}");
        }

        $datasetSha256 = hash_file('sha256', $datasetPath);

        $sourceFiles = [];
        foreach (['datafile', 'summary'] as $key) {
            $path = $context[$key];
            $sourceFiles[] = [
                'file' => 'source/' . basename($path),
                'bytes' => filesize($path),
                'sha256' => hash_file('sha256', $path),
                'modifiedInFile' => $this->readCoreModified($path),
            ];
        }

        $levelCounts = $context['counts'];
        ksort($levelCounts);
        $total = array_sum($levelCounts);

        $manifest = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'datasetFile' => self::DATASET_FILENAME,
            'sourceUrl' => self::SOURCE_URL,
            'originator' => 'Philippine Statistics Authority (PSA)',
            'release' => [
                'psgcRelease' => 'PSGC as of ' . $context['publication']['iso'],
                'publicationDateVerbatim' => $context['publication']['verbatim'],
                'publicationDate' => $context['publication']['iso'],
                'releaseQuarter' => 'Second Quarter 2026',
            ],
            'sources' => $sourceFiles,
            'generatedAt' => gmdate('c'),
            'generator' => [
                'command' => 'php artisan psgc:convert',
                'phpVersion' => PHP_VERSION,
                'schemaVersion' => self::SCHEMA_VERSION,
            ],
            'dataset' => [
                'sha256' => $datasetSha256,
                'bytes' => strlen($json),
                'recordCount' => $total,
            ],
            'counts' => [
                'total' => $total,
                'byLevel' => $levelCounts,
            ],
            'schema' => [
                'format' => 'JSON object with a "nodes" array; nodes sorted ascending by PSGC code',
                'node' => [
                    'code' => '10-digit PSGC code, string, leading zeros significant',
                    'name' => 'official geographic name, UTF-8, surrounding whitespace trimmed',
                    'level' => 'Reg | Prov | City | Mun | SubMun | Bgy, or "" for the two structural rows',
                    'parent' => 'parent node code (string) or null for regions',
                ],
                'levels' => [
                    'Reg' => 'region (root level)',
                    'Prov' => 'province',
                    'City' => 'city (including highly urbanized, independent component and component cities)',
                    'Mun' => 'municipality',
                    'SubMun' => 'sub-municipality / municipal district of the City of Manila (not an official LGU)',
                    'Bgy' => 'barangay',
                    '""' => 'structural container rows: City of Isabela (Not a Province) and Special Geographic Area',
                ],
            ],
            'hierarchy' => [
                'derivation' => 'PSGC coding structure RR-PPP-MM-BBB: barangay parent = first 7 digits + "000"; province parent = region (first 2 digits); city/municipality/sub-municipality parent = first 5 digits + "00000" when that container exists, otherwise the region (highly urbanized cities, Pateros, NCR).',
                'crossValidatedAgainst' => [
                    'PSGC-2Q-2026 National and Provincial Summary sheet (provinces, independent cities, per-container barangay counts)',
                    'PSGC-2Q-2026 National Summary sheet inside the Publication Datafile (per-region counts)',
                ],
                'maxDepth' => 4,
            ],
            'validation' => [
                'duplicateCodes' => 0,
                'invalidCodes' => 0,
                'missingParents' => 0,
                'summaryCrossChecks' => 'passed',
                'officialTotals' => 'passed',
            ],
            'transformations' => [
                'trimmedSurroundingWhitespaceFromNames' => $context['trimmedNames'],
                'preservedOfficialNamesAndCodes' => true,
                'auxiliaryColumnsNotCarried' => [
                    'correspondence code (9-digit)',
                    'old names',
                    'city class',
                    'income classification',
                    'urban/rural',
                    '2024 population',
                    'status (Capital/Pob.)',
                    'footnotes',
                ],
            ],
            'notes' => [
                'Dataset and manifest are generated together by php artisan psgc:convert; psgc.json is byte-deterministic for the same source files.',
                'Source xlsx files live next to this manifest under source/ and are required to regenerate.',
                'No patient data, database schema or application configuration is touched by the conversion.',
            ],
        ];

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($manifestJson === false) {
            throw new RuntimeException('json_encode(manifest) failed: ' . json_last_error_msg());
        }
        $manifestJson .= "\n";

        $manifestPath = $outDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (file_put_contents($manifestPath, $manifestJson, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$manifestPath}");
        }

        return [
            'datasetPath' => $datasetPath,
            'manifestPath' => $manifestPath,
            'datasetBytes' => strlen($json),
            'datasetSha256' => $datasetSha256,
        ];
    }

    private function readCoreModified(string $xlsx): ?string
    {
        $xml = $this->zipEntry($xlsx, 'docProps/core.xml');
        if (preg_match('/<dcterms:modified[^>]*>([^<]+)<\/dcterms:modified>/', $xml, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function zipEntry(string $xlsx, string $entry): string
    {
        $zip = new \ZipArchive();
        $opened = $zip->open($xlsx);
        if ($opened !== true) {
            throw new RuntimeException("Cannot open {$xlsx} (error {$opened})");
        }
        $content = $zip->getFromName($entry);
        $zip->close();
        if ($content === false) {
            throw new RuntimeException("Missing entry {$entry} in " . basename($xlsx));
        }

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $xlsx): array
    {
        $xml = $this->zipEntry($xlsx, 'xl/sharedStrings.xml');
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            throw new RuntimeException('Cannot parse sharedStrings.xml');
        }

        $strings = [];
        foreach ($sx->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } else {
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * Streams rows of a worksheet as column => value maps (shared strings resolved).
     *
     * @param array<int, string> $shared
     *
     * @return \Generator<int, array<string, string>>
     */
    private function rowGenerator(string $sheetXml, array $shared): \Generator
    {
        $reader = new \XMLReader();
        if (!$reader->XML($sheetXml, null, LIBXML_NONET)) {
            throw new RuntimeException('Cannot parse worksheet XML');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            if ($reader->isEmptyElement) {
                yield [];
                continue;
            }

            $cells = [];
            $rowDepth = $reader->depth;
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'row' && $reader->depth === $rowDepth) {
                    break;
                }
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                    continue;
                }

                $ref = $reader->getAttribute('r');
                $type = $reader->getAttribute('t');
                $value = null;

                if (!$reader->isEmptyElement) {
                    $cellDepth = $reader->depth;
                    while ($reader->read()) {
                        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $cellDepth) {
                            break;
                        }
                        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'v') {
                            $value = $reader->readString();
                        } elseif ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'is') {
                            $inline = simplexml_load_string($reader->readOuterXml());
                            $value = $inline === false ? '' : (string)$inline->t;
                        }
                    }
                }

                if ($type === 's' && $value !== null) {
                    $value = $shared[(int)$value] ?? '';
                }
                if ($value === null) {
                    continue;
                }

                $col = preg_replace('/\d+/', '', (string)$ref);
                $cells[$col] = $value;
            }

            yield $cells;
        }

        $reader->close();
    }
}
