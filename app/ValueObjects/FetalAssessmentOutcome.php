<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FetalAssessmentOutcome
{
    /**
     * Chip classes keyed by status. Shared with the live JavaScript config
     * so server-rendered and client-updated chips stay identical.
     */
    public const CHIP_CLASSES = [
        'within' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-800 border-green-200',
        'below' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 border-amber-200',
        'above' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 border-amber-200',
        'review' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 border-amber-200',
        'unable' => 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-300',
    ];

    /**
     * @param  array<string, array{label: string, value: string}>  $rows
     * @param  array<int, array{label: string, url: string}>  $sources
     */
    public function __construct(
        public readonly string $status,
        public readonly string $label,
        public readonly array $rows,
        public readonly string $recommendation,
        public readonly string $reason = '',
        public readonly ?string $clarification = null,
        public readonly string $footnote = '',
        public readonly string $context = '',
        public readonly string $limitation = '',
        public readonly array $sources = [],
        public readonly string $pendingNote = '',
    ) {
    }

    public function chipClass(): string
    {
        return self::CHIP_CLASSES[$this->status] ?? self::CHIP_CLASSES['unable'];
    }
}
