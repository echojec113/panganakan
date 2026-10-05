<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Confirm Patient modal (referrals/select-patient): HIGH RISK REASON payload.
 *
 * The modal already shows the latest assessment date and a HIGH badge. This
 * suite proves the added reason payload is read-only stored evidence taken
 * from the SAME latest assessment (never re-scored, never inferred), that the
 * legacy string/JSON shapes still normalize, and that the empty case renders
 * the neutral fallback instead of inventing a reason.
 */
class ReferralConfirmPatientRiskReasonTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    private function patient(string $first, string $last = 'RiskReason'): Patient
    {
        return Patient::create([
            'first_name' => $first,
            'last_name' => $last,
            'age' => 28,
            'address' => 'Test address',
            'contact_number' => '09171234567',
            'gravida' => 2,
            'para' => 1,
            'status' => 'ONGOING',
        ]);
    }

    private function visit(Patient $patient, array $overrides = []): PrenatalVisit
    {
        return PrenatalVisit::create(array_merge([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateString(),
            'risk_level' => 'HIGH',
        ], $overrides));
    }

    private function selector(): TestResponse
    {
        return $this->actingAs($this->staff)->get(route('referrals.select-patient'));
    }

    /** @return array<int, string> */
    private function reasonsFrom(TestResponse $response): array
    {
        $this->assertSame(
            1,
            preg_match('/data-risk-reasons="([^"]*)"/', $response->getContent(), $matches),
            'Expected exactly one data-risk-reasons attribute in the selector.'
        );

        $decoded = json_decode(
            html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
            true
        );

        $this->assertIsArray($decoded, 'data-risk-reasons must be valid JSON.');

        return $decoded;
    }

    private function attributeFrom(TestResponse $response, string $name): string
    {
        $pattern = '/' . preg_quote($name, '/') . '="([^"]*)"/';

        $this->assertSame(
            1,
            preg_match($pattern, $response->getContent(), $matches),
            "Expected exactly one {$name} attribute in the selector."
        );

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    public function test_modal_reasons_come_from_the_same_latest_assessment(): void
    {
        $patient = $this->patient('Same');

        $this->visit($patient, [
            'visit_date' => now()->subDays(30)->toDateString(),
            'risk_level' => 'HIGH',
            'rule_reasons' => ['Stale older rule'],
            'risk_reasons' => ['Stale older reason'],
        ]);

        $newest = $this->visit($patient, [
            'visit_date' => now()->toDateString(),
            'risk_level' => 'HIGH',
            'decision_source' => 'RULE_BASED',
            'rule_reasons' => ['Anemia'],
            'risk_reasons' => ['Advanced maternal age'],
        ]);

        $response = $this->selector();

        $response->assertOk();
        $response->assertSeeText('HIGH RISK REASON');

        $this->assertSame(
            ['Anemia', 'Advanced maternal age'],
            $this->reasonsFrom($response),
            'Reasons must be the newest assessment\'s stored lists, in profile order.'
        );

        $this->assertSame('HIGH', $this->attributeFrom($response, 'data-risk-level'));
        $this->assertSame(
            $newest->visit_date->format('M d, Y'),
            $this->attributeFrom($response, 'data-assessment')
        );
        $this->assertSame('', $this->attributeFrom($response, 'data-risk-bp'));
        $response->assertDontSee('Stale older', false);
    }

    public function test_bp_urgent_label_is_listed_first_and_flagged(): void
    {
        $patient = $this->patient('BpUrg');

        $this->visit($patient, [
            'risk_level' => 'HIGH',
            'decision_source' => 'RULE_BASED',
            'rule_reasons' => ['Anemia'],
            'bp_assessment' => [
                'reason_code' => 'BP-URG',
                'label' => 'Severe-range blood-pressure finding',
                'verification_status' => 'PENDING_REPEAT',
                'repeat_interpretation' => 'NOT_RECORDED',
            ],
        ]);

        $response = $this->selector();

        $response->assertOk();

        $this->assertSame(
            ['Severe-range blood-pressure finding', 'Anemia'],
            $this->reasonsFrom($response)
        );
        $this->assertSame(
            'Severe-range blood-pressure finding',
            $this->attributeFrom($response, 'data-risk-bp')
        );
    }

    public function test_bp_urgent_label_already_listed_is_not_duplicated(): void
    {
        $patient = $this->patient('BpDup');

        $this->visit($patient, [
            'risk_level' => 'HIGH',
            'rule_reasons' => ['Severe-range blood-pressure finding', 'Anemia'],
            'bp_assessment' => [
                'reason_code' => 'BP-URG',
                'label' => 'Severe-range blood-pressure finding',
            ],
        ]);

        $response = $this->selector();

        $response->assertOk();

        $this->assertSame(
            ['Severe-range blood-pressure finding', 'Anemia'],
            $this->reasonsFrom($response)
        );
    }

    public function test_high_visit_without_stored_reasons_uses_neutral_fallback(): void
    {
        $patient = $this->patient('NoReason');

        $this->visit($patient, [
            'risk_level' => 'HIGH',
            'rule_reasons' => null,
            'risk_reasons' => null,
            'bp_assessment' => null,
        ]);

        $response = $this->selector();

        $response->assertOk();
        $response->assertSee('id="modal-risk-reason-section"', false);
        $response->assertSee('id="modal-risk-reason-list"', false);
        $response->assertSeeText('No specific risk reason recorded.');

        $this->assertSame([], $this->reasonsFrom($response));
        $this->assertSame('HIGH', $this->attributeFrom($response, 'data-risk-level'));
    }

    public function test_legacy_string_and_json_reason_shapes_are_normalized(): void
    {
        $patient = $this->patient('Legacy');

        $this->visit($patient, [
            'risk_level' => 'HIGH',
            'rule_reasons' => 'Birth plan record',
            'risk_reasons' => '["Multiple pregnancy", "", "Advanced maternal age"]',
        ]);

        $response = $this->selector();

        $response->assertOk();

        $this->assertSame(
            ['Birth plan record', 'Multiple pregnancy', 'Advanced maternal age'],
            $this->reasonsFrom($response)
        );
    }

    public function test_low_latest_assessment_never_carries_a_high_reason_payload(): void
    {
        $patient = $this->patient('LowLatest');

        $this->visit($patient, [
            'visit_date' => now()->subDays(7)->toDateString(),
            'risk_level' => 'LOW',
            'rule_reasons' => ['Low-risk stored note'],
        ]);

        $response = $this->selector();

        $response->assertOk();
        $response->assertSee('id="modal-risk-reason-section"', false);
        $response->assertDontSee('data-risk-level="LOW"', false);
        $response->assertDontSee('Low-risk stored note', false);
    }

    public function test_existing_modal_contract_is_unchanged(): void
    {
        $patient = $this->patient('Contract');

        $this->visit($patient, [
            'risk_level' => 'HIGH',
            'rule_reasons' => ['Anemia'],
        ]);

        $response = $this->selector();

        $response->assertOk();
        $response->assertSee('id="referral-confirmation-modal"', false);
        $response->assertSee('id="modal-assessment"', false);
        $response->assertSee('id="modal-risk-reason-section" class="hidden"', false);
        $response->assertSee('data-patient="Contract RiskReason"', false);
        $response->assertSee(route('referrals.create', $patient->id), false);
        $response->assertSee('HIGH', false);
    }
}
