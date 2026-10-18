<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Ultrasound;
use App\Models\User;
use App\Services\PatientAssessmentRecalculationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Kept outside Feature to avoid its global RefreshDatabase/migration hook.
class UltrasoundPresentationRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // Verifies SQLite :memory: before any fixture writes.

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('ultrasounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            foreach (['scan_date', 'gestational_age_scan', 'estimated_fetal_weight',
                'fetal_heartbeat', 'fetal_movement', 'presentation', 'amniotic_fluid',
                'placenta_position', 'remarks'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('module');
            $table->text('description');
            $table->timestamps();
        });

        $this->mock(PatientAssessmentRecalculationService::class)
            ->shouldReceive('recalculateIncompleteVisits')->andReturnNull();
    }

    public static function presentations(): array
    {
        return array_map(fn ($value) => [$value], [
            'Not visible yet', 'Cephalic', 'Breech', 'Transverse', 'Oblique',
        ]);
    }

    private function record(string $presentation = 'Not visible yet'): Ultrasound
    {
        $patient = Patient::create(['first_name' => 'Synthetic', 'last_name' => 'Fixture']);

        return Ultrasound::create([
            'patient_id' => $patient->id,
            'scan_date' => today()->toDateString(),
            'gestational_age_scan' => 22.5,
            'estimated_fetal_weight' => 500,
            'fetal_heartbeat' => 'Normal 120-160',
            'fetal_movement' => 'Active',
            'presentation' => $presentation,
            'amniotic_fluid' => 'Normal',
            'placenta_position' => 'Posterior',
            'remarks' => 'Original fixture remarks',
        ]);
    }

    private function staff(string $role = 'staff'): User
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'name' => 'Test actor', 'role' => $role]);

        return $user;
    }

    private function presentationSelect(string $form, ?Ultrasound $ultrasound = null): \DOMElement
    {
        // Render the actual Blade select, avoiding unrelated layout/database dependencies.
        $source = file_get_contents(resource_path("views/ultrasounds/{$form}.blade.php"));
        $this->assertSame(1, preg_match('/<select\s+name="presentation".*?<\/select>/s', $source, $match));
        $html = Blade::render($match[0], compact('ultrasound'));
        $dom = new \DOMDocument;
        $dom->loadHTML($html);

        return $dom->getElementsByTagName('select')->item(0);
    }

    public function test_create_and_edit_expose_identical_required_options(): void
    {
        $record = $this->record();
        foreach (['create', 'edit'] as $form) {
            $select = $this->presentationSelect($form, $record);
            $this->assertTrue($select->hasAttribute('required'));
            $values = [];
            foreach ($select->getElementsByTagName('option') as $option) {
                $values[] = $option->getAttribute('value');
            }
            $this->assertSame(['', 'Not visible yet', 'Cephalic', 'Breech', 'Transverse', 'Oblique'], $values);
        }
    }

    #[DataProvider('presentations')]
    public function test_saved_selection_survives_unchanged_save_and_remarks_edit(string $presentation): void
    {
        $record = $this->record($presentation)->fresh();
        $selected = [];
        foreach ($this->presentationSelect('edit', $record)->getElementsByTagName('option') as $option) {
            if ($option->hasAttribute('selected')) {
                $selected[] = $option->getAttribute('value');
            }
        }
        $this->assertSame([$presentation], $selected);
        $payload = $record->only($record->getFillable());
        $payload['scan_date'] = $record->scan_date->toDateString();
        $payload['presentation'] = $selected[0];

        foreach (['Original fixture remarks', 'Edited fixture remarks'] as $remarks) {
            $payload['remarks'] = $remarks;
            $this->actingAs($this->staff())->put(route('ultrasound.update', $record->id), $payload)
                ->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $record->patient_id));
            $this->assertSame($presentation, $record->fresh()->presentation);
            $this->assertSame($remarks, $record->fresh()->remarks);
        }
    }

    public function test_old_input_takes_precedence_over_saved_presentation(): void
    {
        $this->withSession(['_old_input' => ['presentation' => 'Breech']]);
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $selected = [];
        foreach ($this->presentationSelect('edit', $this->record())->getElementsByTagName('option') as $option) {
            if ($option->hasAttribute('selected')) {
                $selected[] = $option->getAttribute('value');
            }
        }
        $this->assertSame(['Breech'], $selected);
    }

    public function test_guest_and_non_staff_cannot_edit_or_update(): void
    {
        $record = $this->record();
        $this->get(route('ultrasound.edit', $record->id))->assertRedirect(route('login'));
        $this->put(route('ultrasound.update', $record->id), [])->assertRedirect(route('login'));
        foreach (['patient', 'admin', 'unknown'] as $role) {
            $this->actingAs($this->staff($role))->get(route('ultrasound.edit', $record->id))->assertForbidden();
            $this->put(route('ultrasound.update', $record->id), [])->assertForbidden();
        }
        $this->assertSame('Not visible yet', $record->fresh()->presentation);
        $this->assertSame('Original fixture remarks', $record->fresh()->remarks);
    }

    public function test_update_cannot_reassign_record_to_submitted_patient(): void
    {
        $record = $this->record();
        $other = $this->record('Breech');
        $payload = $record->only($record->getFillable());
        $payload['scan_date'] = $record->scan_date->toDateString();
        $payload['patient_id'] = $other->patient_id;
        $this->actingAs($this->staff())->put(route('ultrasound.update', $record->id), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $record->patient_id));
        $this->assertSame($record->patient_id, $record->fresh()->patient_id);
        $this->assertSame('Breech', $other->fresh()->presentation);
        $this->get(route('ultrasound.edit', 999999))->assertNotFound();
        $this->put(route('ultrasound.update', 999999), $payload)->assertNotFound();
    }
}
