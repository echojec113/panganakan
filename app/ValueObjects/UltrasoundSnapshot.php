<?php

namespace App\ValueObjects;

use App\Models\Ultrasound;
use Carbon\CarbonImmutable;

/**
 * Controlled, immutable snapshot of the only ultrasound values a clinical rule
 * may consume.
 *
 * Carries the exact record identity and the three evaluated findings. It never
 * carries an Eloquent model, patient data, PII, or free-text notes, so it is a
 * safe input for the rule engine and a faithful representation of what the
 * persisted assessment context captured.
 */
class UltrasoundSnapshot
{
    public readonly ?int $id;
    public readonly ?string $date;
    public readonly string $presentation;
    public readonly string $amniotic_fluid;
    public readonly string $fetal_heartbeat;

    public function __construct(
        ?int $id,
        ?string $date,
        string $presentation,
        string $amniotic_fluid,
        string $fetal_heartbeat,
    ) {
        $this->id = $id;
        $this->date = $date;
        $this->presentation = self::normalize($presentation);
        $this->amniotic_fluid = self::normalize($amniotic_fluid);
        $this->fetal_heartbeat = self::normalize($fetal_heartbeat);
    }

    public static function fromModel(?Ultrasound $ultrasound): ?self
    {
        if ($ultrasound === null) {
            return null;
        }

        return new self(
            id: $ultrasound->id !== null ? (int) $ultrasound->id : null,
            date: $ultrasound->scan_date ? CarbonImmutable::parse($ultrasound->scan_date)->toDateString() : null,
            presentation: (string) $ultrasound->presentation,
            amniotic_fluid: (string) $ultrasound->amniotic_fluid,
            fetal_heartbeat: (string) $ultrasound->fetal_heartbeat,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'presentation' => $this->presentation,
            'amniotic_fluid' => $this->amniotic_fluid,
            'fetal_heartbeat' => $this->fetal_heartbeat,
        ];
    }

    /**
     * The three clinical inputs only (no id/date), for the context metadata.
     *
     * @return array<string, string>
     */
    public function inputs(): array
    {
        return [
            'presentation' => $this->presentation,
            'amniotic_fluid' => $this->amniotic_fluid,
            'fetal_heartbeat' => $this->fetal_heartbeat,
        ];
    }

    private static function normalize(string $value): string
    {
        return trim($value);
    }
}