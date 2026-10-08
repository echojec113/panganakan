<?php

namespace App\Http\Controllers;

use App\Services\PsgcIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Read-only PSGC location search endpoint backing the address autocomplete.
 *
 * Design decisions (Phase 2, extended in Phase 3 with approval):
 * - Lives inside the existing ['auth', 'no-store'] group, which is the
 *   project's Admin & Staff access convention (roles are admin and staff
 *   only; users.role defaults to 'staff'), so guests never receive data.
 * - Delegates entirely to PsgcIndex::search(): identical trimming,
 *   normalization, ranking and limits as the Phase 1 service contract.
 *   Response rows are the service rows plus one derived field: `city`,
 *   the resolved city/municipality name (Phase 3 approval), so the client
 *   never has to split the label to autofill the city input. Field names,
 *   official PSGC codes as strings, and official level codes are not
 *   re-mapped.
 * - The 'no-store' middleware deliberately skips non-HTML responses, so the
 *   Cache-Control header is applied here with the same directive value.
 * - Never returns filesystem paths or exception details: input problems are
 *   answered with generic JSON errors, and a dataset failure is masked as a
 *   generic 503.
 */
class LocationSearchController extends Controller
{
    /** Maximum suggestions returned per request (Phase 1 default limit). */
    public const MAX_RESULTS = 12;

    /** Maximum accepted query length in Unicode characters. */
    public const MAX_QUERY_LENGTH = 100;

    /** Same directive value the project's SetNoStoreHeaders middleware uses. */
    private const NO_STORE = 'no-store, no-cache, must-revalidate, private';

    /**
     * GET /locations/search?q=...
     *
     * - missing/empty/short/unknown query -> 200 {"results": []}
     * - valid query -> up to 12 suggestion rows
     * - non-text or excessively long query -> 422 with a generic message
     */
    public function search(Request $request, PsgcIndex $index): JsonResponse
    {
        $query = $request->query('q');

        if ($query === null) {
            return $this->ok([]);
        }

        if (!is_string($query)) {
            return $this->error('The search query must be text.', 422);
        }

        $length = mb_strlen($query, 'UTF-8');
        if (false === $length || $length > self::MAX_QUERY_LENGTH) {
            return $this->error('The search query is too long.', 422);
        }

        try {
            $results = $index->search($query, self::MAX_RESULTS);
        } catch (RuntimeException) {
            // Dataset missing or unreadable: answer generically instead of
            // exposing the stored path or the underlying exception.
            return $this->error('Location search is temporarily unavailable.', 503);
        }

        return $this->ok(array_map(
            fn (array $row): array => $this->withCity($index, $row),
            $results
        ));
    }

    /**
     * Adds the resolved city/municipality name to a search row.
     *
     * `city` is the name of the nearest ancestor-or-self whose level is
     * City or Mun. Sub-municipality districts (the 14 districts of the City
     * of Manila) resolve upward to their city; region, province and the two
     * structural container rows resolve to null. The client uses this for
     * the barangay -> city autofill without ever parsing `label`.
     *
     * @param array{code: string, name: string, level: string, parent: ?string, label: string} $row
     *
     * @return array{code: string, name: string, level: string, parent: ?string, city: ?string, label: string}
     */
    private function withCity(PsgcIndex $index, array $row): array
    {
        $city = null;

        foreach (array_reverse($index->hierarchy($row['code'])) as $node) {
            if ($node['level'] === 'City' || $node['level'] === 'Mun') {
                $city = $node['name'];
                break;
            }
        }

        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'level' => $row['level'],
            'parent' => $row['parent'],
            'city' => $city,
            'label' => $row['label'],
        ];
    }

    /**
     * @param array<int, array{code: string, name: string, level: string, parent: ?string, city: ?string, label: string}> $results
     */
    private function ok(array $results): JsonResponse
    {
        return response()->json(['results' => $results])
            ->header('Cache-Control', self::NO_STORE);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status)
            ->header('Cache-Control', self::NO_STORE);
    }
}
