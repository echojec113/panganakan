<?php

namespace App\Services;

use Carbon\Carbon;

abstract class AnalyticsService
{
    protected const MONTH_WINDOW = 12;

    /**
     * Bucket rows into a rolling monthly series (latest calendar months,
     * zero-filled, chronological). If fewer months of data exist than the
     * window, the series starts at the earliest data month.
     */
    protected function monthlySeries(array $rows, string $dateColumn, int $windowMonths = self::MONTH_WINDOW): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->{$dateColumn})->format('Y-m');
            $groups[$key][] = $row;
        }

        if ($groups === []) {
            return ['keys' => [], 'labels' => [], 'groups' => []];
        }

        $present = array_keys($groups);
        sort($present, SORT_STRING);

        $latest = Carbon::createFromFormat('Y-m', $present[count($present) - 1])->startOfMonth();
        $windowStart = $latest->copy()->subMonths($windowMonths - 1)->startOfMonth();
        $earliest = Carbon::createFromFormat('Y-m', $present[0])->startOfMonth();

        if ($earliest->greaterThan($windowStart)) {
            $windowStart = $earliest->copy();
        }

        $keys = [];
        $cursor = $windowStart->copy();

        while (!$cursor->greaterThan($latest)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return [
            'keys' => $keys,
            'labels' => array_map(fn (string $key) => Carbon::createFromFormat('Y-m', $key)->format('M Y'), $keys),
            'groups' => $groups,
        ];
    }

    /**
     * Ordered keys/labels for every calendar month in a given year (Jan–Dec).
     */
    protected function monthsInYear(int $year): array
    {
        $keys = [];
        $labels = [];
        $cursor = Carbon::create($year, 1, 1)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $cursor->addMonth();
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    /**
     * Calendar month buckets for a given year.
     *
     * With $month === null the bucket list is the full January–December axis
     * (always 12 keys, short labels such as "Jan".."Dec", regardless of which
     * months actually contain data). With a specific month it is a single
     * "Y-m" bucket labelled "M Y".
     */
    protected function monthBuckets(int $year, ?int $month): array
    {
        if ($month === null) {
            $keys = [];
            $labels = [];
            $cursor = Carbon::create($year, 1, 1)->startOfMonth();

            for ($i = 0; $i < 12; $i++) {
                $keys[] = $cursor->format('Y-m');
                $labels[] = $cursor->format('M');
                $cursor->addMonth();
            }

            return ['keys' => $keys, 'labels' => $labels];
        }

        $date = Carbon::create($year, $month, 1);

        return [
            'keys' => [$date->format('Y-m')],
            'labels' => [$date->format('M Y')],
        ];
    }

    /**
     * Highest-count period; ties resolved to the earliest period.
     */
    protected function maxPeriod(array $labels, array $data): ?array
    {
        if ($data === []) {
            return null;
        }

        $maxValue = max($data);

        if ($maxValue <= 0) {
            return null;
        }

        return [
            'label' => $labels[array_search($maxValue, $data, true)],
            'count' => $maxValue,
        ];
    }

    protected function normalizeLabel(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }

    /**
     * Group raw strings by normalized key, keep first-seen spelling, return top N.
     */
    protected function groupedTop(array $values, int $limit): array
    {
        $groups = [];

        foreach ($values as $value) {
            $display = trim((string) $value);
            $key = $this->normalizeLabel($display);

            if ($key === '') {
                continue;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $display, 'count' => 0];
            }

            $groups[$key]['count']++;
        }

        $items = array_values($groups);

        usort($items, function (array $a, array $b) {
            return $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']);
        });

        return array_slice($items, 0, $limit);
    }

    protected function mostCommon(array $items, string $nameKey = 'label'): ?array
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['count'] > 0));

        if ($items === []) {
            return null;
        }

        usort($items, function (array $a, array $b) use ($nameKey) {
            return $b['count'] <=> $a['count'] ?: strcmp((string) $a[$nameKey], (string) $b[$nameKey]);
        });

        return $items[0];
    }
}
