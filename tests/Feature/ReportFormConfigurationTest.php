<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AuditEvent;
use App\Models\ReportFormTemplate;
use App\Models\User;
use App\Services\OfficialEquipmentSummaryService;
use App\Support\OfficialReportTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFormConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsHead(): User
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);

        return $head;
    }

    private function active(): ReportFormTemplate
    {
        return ReportFormTemplate::query()->where('status', 'ACTIVE')->firstOrFail();
    }

    /** The payload the editor submits for an unchanged copy of $source. */
    private function payload(ReportFormTemplate $source, array $overrides = []): array
    {
        $config = $source->configuration_json;
        $columns = collect($config['columns'])->keyBy('key');

        return array_replace_recursive([
            'source_template_id' => $source->id,
            'report_key' => $source->report_key,
            'form_code' => $source->form_code,
            'effectivity_date' => $source->effectivity_date?->format('Y-m-d'),
            'revision_label' => $source->revision_label,
            'show_logo' => '1',
            'header_republic' => $config['header']['republic'],
            'header_institution' => $config['header']['institution'],
            'header_location' => $config['header']['location'],
            'column_labels' => $columns->map->label->all(),
            'column_order' => $columns->map->order->all(),
            'column_visibility' => $columns->map(fn ($c) => $c['visible'] ? '1' : '0')->all(),
            'formula_enabled' => '1',
            'prepared_by_label' => $config['signatories']['prepared_by_label'],
            'prepared_by_name' => $config['signatories']['prepared_by_name'],
            'prepared_by_designation' => $config['signatories']['prepared_by_designation'],
            'noted_by_label' => $config['signatories']['noted_by_label'],
            'noted_by_name' => $config['signatories']['noted_by_name'],
            'noted_by_designation' => $config['signatories']['noted_by_designation'],
            'show_signature_date' => '1',
            'page_size' => $config['page']['size'],
            'orientation' => $config['page']['orientation'],
        ], $overrides);
    }

    public function test_page_renders_editor_for_active_revision(): void
    {
        $this->actingAsHead();

        $this->get(route('policies.report-forms.index'))
            ->assertOk()
            ->assertSee('This is the official form currently in use.')
            ->assertSee('Save as Draft')
            ->assertSee('data-rfc-editor', false);
    }

    public function test_saving_from_active_creates_a_draft_and_leaves_active_unchanged(): void
    {
        $this->actingAsHead();
        $active = $this->active();

        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'revision_label' => 'Rev. 3',
            'column_labels' => ['remarks' => 'Status / Remarks'],
            'column_order' => ['remarks' => 1, 'control_no' => 10],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->assertSame(2, $draft->version_no);
        $this->assertSame('Rev. 3', $draft->revision_label);
        $this->assertSame('remarks', $draft->configuration_json['columns'][0]['key']);
        $this->assertSame('Status / Remarks', $draft->configuration_json['columns'][0]['label']);
        $this->assertSame('Rev. 2', $active->fresh()->revision_label);

        // The redirected page opens the Draft with its activation review.
        $this->get(route('policies.report-forms.index'))
            ->assertOk()
            ->assertSee('Review &amp; Activate', false)
            ->assertSee('Working Draft · Version 2')
            ->assertSee('Activate Version 2')
            ->assertSee('Save Draft');
    }

    public function test_saving_the_draft_again_updates_it_instead_of_creating_versions(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active()));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $this->post(route('policies.report-forms.store'), $this->payload($draft, [
            'noted_by_name' => 'NEW HEAD, PhD',
            'change_reason' => 'New SPMU Head.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, ReportFormTemplate::query()->count());
        $this->assertSame('NEW HEAD, PhD', $draft->fresh()->configuration_json['signatories']['noted_by_name']);
    }

    public function test_active_revision_cannot_overwrite_an_existing_draft(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, ['revision_label' => 'Rev. 3']));

        $this->post(route('policies.report-forms.store'), $this->payload($active, ['revision_label' => 'Rev. 9']))
            ->assertSessionHasErrors('source_template_id');

        $this->assertSame('Rev. 3', ReportFormTemplate::query()->where('status', 'DRAFT')->value('revision_label'));
    }

    public function test_draft_saves_without_manually_entering_reason(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'revision_label' => 'Rev. 3',
        ]))->assertSessionHasNoErrors();

        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->assertStringContainsString('revision label', $draft->change_reason);
        $this->assertTrue(AuditEvent::query()->where('action_code', 'REPORT_FORM_REVISION_CREATED')->exists());
    }

    public function test_activating_the_draft_archives_the_previous_active_revision(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), ['revision_label' => 'Rev. 3']));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasNoErrors();

        $this->assertSame('ACTIVE', $draft->fresh()->status);
        $this->assertSame('ARCHIVED', ReportFormTemplate::query()->where('version_no', 1)->value('status'));

        $this->get(route('reports.official.equipment', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Rev. 3');
    }

    public function test_incomplete_draft_cannot_be_activated(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), ['noted_by_name' => '']));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasErrors('signatories');

        $this->assertSame('DRAFT', $draft->fresh()->status);
    }

    public function test_draft_can_be_discarded_but_active_cannot(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $this->post(route('policies.report-forms.discard', $draft))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(ReportFormTemplate::query()->find($draft->id));
        $this->assertTrue(AuditEvent::query()->where('action_code', 'REPORT_FORM_DRAFT_DISCARDED')->exists());

        $this->post(route('policies.report-forms.discard', $active))->assertStatus(422);
        $this->assertSame('ACTIVE', $active->fresh()->status);
    }

    public function test_archived_revision_must_be_copied_before_editing(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active()));
        $this->post(route('policies.report-forms.activate', ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail()));
        $archived = ReportFormTemplate::query()->where('status', 'ARCHIVED')->firstOrFail();

        $this->post(route('policies.report-forms.store'), $this->payload($archived, ['change_reason' => 'Edit archived.']))
            ->assertSessionHasErrors('source_template_id');

        $this->post(route('policies.report-forms.duplicate', $archived))->assertRedirect();
        $this->assertSame(1, ReportFormTemplate::query()->where('status', 'DRAFT')->count());
    }

    public function test_page_has_no_editable_title_or_manual_change_reason(): void
    {
        $this->actingAsHead();
        $this->get(route('policies.report-forms.index'))
            ->assertOk()
            ->assertDontSee('name="title_template"', false)
            ->assertDontSee('name="change_reason"', false)
            ->assertSee('The official report title is set by the system.')
            ->assertSee('data-rfc-live-preview', false);
    }

    public function test_legacy_placeholder_title_is_kept_on_active_and_saved_plain_on_the_draft(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->assertTrue(OfficialReportTitle::isLegacy($active->title_template));

        $this->get(route('policies.report-forms.index'))->assertDontSee('name="title_template"', false);

        $this->post(route('policies.report-forms.store'), $this->payload($active, ['revision_label' => 'Rev. 3']))
            ->assertSessionHasNoErrors();

        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->assertSame('Summary of Requests for Equipment', $draft->title_template);
        $this->assertSame('Summary of Requests for Equipment for the month of {month_year}', $active->fresh()->title_template);
    }

    public function test_posted_report_title_override_is_ignored(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'title_template' => 'Incorrect user-supplied report title',
        ]))->assertSessionHasNoErrors();
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->assertSame(OfficialReportTitle::DEFAULT_TITLE, $draft->title_template);
    }

    public function test_new_revision_keeps_the_official_form_code(): void
    {
        $this->actingAsHead();

        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), ['revision_label' => 'Rev. 3']))
            ->assertSessionHasNoErrors();
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasNoErrors();
        $this->post(route('policies.report-forms.duplicate', ReportFormTemplate::query()->where('status', 'ARCHIVED')->firstOrFail()))
            ->assertRedirect();

        $this->assertSame(3, ReportFormTemplate::query()->count());
        $this->assertSame(['CSPC-F-SPMU-22'], ReportFormTemplate::query()->pluck('form_code')->unique()->values()->all());
    }

    public function test_editor_shows_live_preview_and_supported_column_catalog(): void
    {
        $this->actingAsHead();

        $this->get(route('policies.report-forms.index'))
            ->assertOk()
            ->assertSee('data-rfc-live-preview', false)
            ->assertSee('data-rfc-add-search', false)
            ->assertSee('Search a system field or type a custom heading...')
            ->assertDontSee('data-rfc-add-select', false)
            ->assertSee('data-rfc-remove-column', false)
            ->assertDontSee('id="rfc-section-header"', false)
            ->assertSee('A revised code is saved to the Draft only.');
    }

    public function test_optional_system_fields_can_be_added_to_a_new_draft(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'column_visibility' => ['borrower_name' => '1', 'requested_quantity' => '1'],
        ]))->assertSessionHasNoErrors();

        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $columns = collect($draft->configuration_json['columns'])->keyBy('key');
        $this->assertTrue($columns->get('borrower_name')['visible']);
        $this->assertTrue($columns->get('requested_quantity')['visible']);
        $this->assertFalse($columns->get('approved_date')['visible']);
    }

    public function test_editor_catalogue_matches_the_fields_the_report_can_fill(): void
    {
        $definitions = (new \ReflectionClass(\App\Http\Controllers\ReportFormConfigurationController::class))
            ->getReflectionConstant('COLUMN_DEFINITIONS')->getValue();

        $this->assertSame(OfficialEquipmentSummaryService::KEYS, array_keys($definitions));
        $this->assertCount(19, $definitions);
        $this->assertSame(OfficialEquipmentSummaryService::LABELS, array_map(fn (array $d): string => $d['label'], $definitions));

        // The official ten are included by default; every other field is opt-in.
        $optional = array_keys(array_filter($definitions, fn (array $d): bool => ($d['visible'] ?? true) === false));
        $this->assertSame(OfficialEquipmentSummaryService::OPTIONAL_KEYS, $optional);

        // The editor previews carry a sample value for every field.
        $this->actingAsHead();
        $page = $this->get(route('policies.report-forms.index'))->assertOk();
        foreach (OfficialEquipmentSummaryService::KEYS as $key) {
            $page->assertSee($key.': ', false);
        }
    }

    public function test_new_fields_reach_the_official_report_only_through_an_activated_draft(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $approved = $active->configuration_json;
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $columns = fn (): array => array_column(
            app(OfficialEquipmentSummaryService::class)->build(now()->startOfMonth(), now()->endOfMonth())['columns'],
            'key'
        );

        // Saving an unchanged copy lists every supported field, with the new ones excluded.
        $this->post(route('policies.report-forms.store'), $this->payload($active))->assertSessionHasNoErrors();
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $stored = collect($draft->configuration_json['columns'])->keyBy('key');
        $this->assertSame(OfficialEquipmentSummaryService::KEYS, $stored->keys()->all());
        foreach (OfficialEquipmentSummaryService::OPTIONAL_KEYS as $key) {
            $this->assertFalse($stored->get($key)['visible'], $key);
        }

        // Including fields in the Draft does not touch the official report.
        $this->post(route('policies.report-forms.store'), $this->payload($draft, [
            'column_visibility' => ['purpose_event' => '1', 'approved_by' => '1'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(OfficialEquipmentSummaryService::OFFICIAL_KEYS, $columns());
        $this->get(route('reports.official.equipment.export', $query + ['format' => 'print']))
            ->assertOk()->assertDontSee('Purpose / Event')->assertDontSee('Approved By');

        // Activation is what publishes them.
        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasNoErrors();
        $this->assertSame([...OfficialEquipmentSummaryService::OFFICIAL_KEYS, 'purpose_event', 'approved_by'], $columns());
        $this->get(route('reports.official.equipment.export', $query + ['format' => 'print']))
            ->assertOk()->assertSee('Purpose / Event')->assertSee('Approved By')
            ->assertDontSee('Venue / Location')->assertDontSee('Custody No.');

        // The superseded revision is archived exactly as it was approved.
        $archived = $active->fresh();
        $this->assertSame('ARCHIVED', $archived->status);
        $this->assertSame($approved, $archived->configuration_json);
    }

    /** @return \Illuminate\Support\Collection<string, array> stored columns of the working Draft, by key */
    private function draftColumns(): \Illuminate\Support\Collection
    {
        return collect(ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail()->configuration_json['columns'])
            ->keyBy('key');
    }

    public function test_custom_column_keeps_its_key_heading_and_order_through_draft_and_activation(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $approved = $active->configuration_json;
        $key = 'custom_a1b2c3d4e5';
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $resolved = fn (): array => array_column(
            app(OfficialEquipmentSummaryService::class)->build(now()->startOfMonth(), now()->endOfMonth())['columns'],
            'label',
            'key'
        );

        // Added between Control No. (1) and Requesting Office (2 -> 3).
        $order = collect($approved['columns'])->mapWithKeys(fn ($c) => [$c['key'] => $c['order'] >= 2 ? $c['order'] + 1 : $c['order']])->all();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'column_labels' => [$key => 'Received By (Signature)'],
            'column_order' => $order + [$key => 2],
            'column_visibility' => [$key => '1'],
        ]))->assertSessionHasNoErrors();

        $stored = $this->draftColumns();
        $this->assertSame(
            ['key' => $key, 'label' => 'Received By (Signature)', 'visible' => true, 'order' => 2, 'group' => null, 'custom' => true],
            $stored->get($key)
        );
        $this->assertSame(['control_no', $key, 'requesting_office'], $stored->keys()->take(3)->all());
        // System columns are stored exactly as before: no custom marker.
        $this->assertArrayNotHasKey('custom', $stored->get('control_no'));

        // Renaming changes the heading only; the key and position stay.
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->post(route('policies.report-forms.store'), $this->payload($draft, [
            'column_labels' => [$key => 'Acknowledged By'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Acknowledged By', $this->draftColumns()->get($key)['label']);
        $this->assertSame(2, $this->draftColumns()->get($key)['order']);
        $this->assertCount(20, $this->draftColumns());

        // The Draft does not reach the official report.
        $this->assertArrayNotHasKey($key, $resolved());
        $this->get(route('reports.official.equipment.export', $query + ['format' => 'print']))
            ->assertOk()->assertDontSee('Acknowledged By');

        // The editor reopens the Draft with the custom column marked as unbound.
        $this->get(route('policies.report-forms.index'))->assertOk()
            ->assertSee('data-column-key="'.$key.'"', false)
            ->assertSee('value="Acknowledged By"', false)
            ->assertSee('rfc-source-chip is-custom', false)
            ->assertSee('<template data-rfc-custom-template>', false)
            ->assertSee('id="rfc-add-search"', false);

        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasNoErrors();
        $this->assertSame(['control_no', $key, 'requesting_office'], array_slice(array_keys($resolved()), 0, 3));
        $this->assertSame('Acknowledged By', $resolved()[$key]);
        $this->get(route('reports.official.equipment.export', $query + ['format' => 'print']))
            ->assertOk()->assertSee('Acknowledged By');

        // The superseded revision is archived as approved and offers no editing tools.
        $archived = $active->fresh();
        $this->assertSame($approved, $archived->configuration_json);
        $this->get(route('policies.report-forms.index', ['template' => $archived->id]))->assertOk()
            ->assertDontSee('data-column-key="'.$key.'"', false)
            ->assertDontSee('<template data-rfc-custom-template>', false)
            ->assertDontSee('id="rfc-add-search"', false);
    }

    public function test_every_heading_can_be_renamed_including_control_no(): void
    {
        $this->actingAsHead();
        $labels = collect(OfficialEquipmentSummaryService::KEYS)->mapWithKeys(fn ($key) => [$key => 'Renamed '.$key])->all();

        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), [
            'column_labels' => $labels,
        ]))->assertSessionHasNoErrors();

        $stored = $this->draftColumns();
        $this->assertSame($labels, $stored->map->label->all());
        $this->assertSame(OfficialEquipmentSummaryService::KEYS, $stored->keys()->all());
        $this->assertTrue($stored->get('control_no')['visible']);
        $this->assertSame('RECEIVED', $stored->get('received_date')['group']);
    }

    public function test_removed_custom_column_is_dropped_from_the_draft(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), [
            'column_labels' => ['custom_keepme01' => 'Keep', 'custom_dropme01' => 'Drop', 'custom_hidden01' => 'Hidden'],
            'column_order' => ['custom_keepme01' => 11, 'custom_dropme01' => 12, 'custom_hidden01' => 13],
            'column_visibility' => ['custom_hidden01' => '0'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['custom_keepme01', 'custom_dropme01'], $this->draftColumns()->filter(fn ($c) => $c['custom'] ?? false)->keys()->all());

        // The editor posts only the rows still present.
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $payload = $this->payload($draft);
        foreach (['column_labels', 'column_order', 'column_visibility'] as $field) {
            unset($payload[$field]['custom_dropme01']);
        }
        $this->post(route('policies.report-forms.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(['custom_keepme01'], $this->draftColumns()->filter(fn ($c) => $c['custom'] ?? false)->keys()->all());
        $this->assertCount(20, $this->draftColumns());
    }

    public function test_custom_column_headings_and_identifiers_are_validated(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $attempt = fn (array $labels) => $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'column_labels' => $labels,
            'column_order' => array_fill_keys(array_keys($labels), 11),
        ]));

        foreach (['evil', 'custom_AB12CD', 'custom_ab', 'custom_'.str_repeat('a', 25), 'custom_ab12cd;x', '_link', 'remarks2'] as $key) {
            $attempt([$key => 'Heading'])->assertSessionHasErrors('column_labels');
        }
        $attempt(['custom_blank001' => '   '])->assertSessionHasErrors('column_labels.custom_blank001');
        $attempt(['custom_toolong1' => str_repeat('x', 121)])->assertSessionHasErrors('column_labels.custom_toolong1');
        $attempt(['custom_newline1' => "Two\nLines"])->assertSessionHasErrors('column_labels.custom_newline1');
        $attempt(['control_no' => ''])->assertSessionHasErrors('column_labels.control_no');

        $tooMany = [];
        for ($i = 0; $i <= OfficialEquipmentSummaryService::MAX_CUSTOM_COLUMNS; $i++) {
            $tooMany[sprintf('custom_many%04d', $i)] = 'Extra '.$i;
        }
        $attempt($tooMany)->assertSessionHasErrors('column_labels');

        $this->assertSame(0, ReportFormTemplate::query()->where('status', 'DRAFT')->count());

        // After a failed save the editor still shows the column that was being added.
        $attempt(['custom_retry001' => 'Retry Me', 'custom_toolong1' => str_repeat('x', 121)]);
        $this->get(route('policies.report-forms.index'))->assertOk()
            ->assertSee('data-column-key="custom_retry001"', false)
            ->assertSee('value="Retry Me"', false);
    }

    /** The opening tag of the element carrying $attribute, e.g. to check for `hidden` or `disabled`. */
    private function openingTag(string $html, string $attribute): string
    {
        $this->assertSame(1, preg_match('/<[a-z]+\b[^>]*\b'.preg_quote($attribute, '/').'[^>]*>/s', $html, $match), $attribute.' not found');

        return $match[0];
    }

    private function hasFlag(string $tag, string $flag): bool
    {
        // A bare boolean attribute, not a class name or data-* attribute containing the word.
        return preg_match('/\s'.$flag.'(\s|>|=)/', $tag) === 1;
    }

    public function test_active_revision_shows_the_save_card_and_no_draft_actions(): void
    {
        $this->actingAsHead();
        $html = $this->get(route('policies.report-forms.index'))->assertOk()
            ->assertSee('Save as Draft')
            ->assertSee('No unsaved changes')
            ->assertDontSee('id="rfc-review"', false)
            ->assertDontSee('Discard Draft')
            ->assertDontSee('Activate Version')
            ->getContent();

        $this->assertFalse($this->hasFlag($this->openingTag($html, 'data-rfc-savebar'), 'hidden'));
    }

    public function test_saved_complete_draft_shows_review_and_activate_instead_of_the_save_card(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), ['revision_label' => 'Rev. 3']));

        $html = $this->get(route('policies.report-forms.index'))->assertOk()
            ->assertSee('Working Draft · Version 2')
            ->assertSee('6/6 Complete')
            ->assertSee('Preview Draft')
            ->assertSee('Discard Draft')
            ->assertSee('Activate Version 2')
            ->assertSee('Undo Changes')
            ->assertDontSee('Required before activation')
            ->assertDontSee('Save the Draft first')
            ->getContent();

        // One card at a time: the save card waits, hidden, until something changes.
        $this->assertTrue($this->hasFlag($this->openingTag($html, 'data-rfc-savebar'), 'hidden'));
        $this->assertFalse($this->hasFlag($this->openingTag($html, 'data-rfc-review'), 'hidden'));
        $activate = $this->openingTag($html, 'data-rfc-requires-saved');
        $this->assertFalse($this->hasFlag($activate, 'disabled'));
        $this->assertStringContainsString('data-rfc-ready="1"', $activate);
        $this->assertStringContainsString('data-rfc-confirm="Activate Version 2', $activate);
        $this->assertStringContainsString('data-rfc-confirm="Discard working Draft Version 2', $html);
        $this->assertSame(1, substr_count($html, 'report-forms/'.ReportFormTemplate::query()->where('status', 'DRAFT')->value('id').'/activate'));
    }

    public function test_incomplete_draft_shows_only_what_is_missing_and_cannot_be_activated(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), [
            'noted_by_name' => '', 'revision_label' => '',
        ]));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $html = $this->get(route('policies.report-forms.index'))->assertOk()
            ->assertSee('4/6 Complete')
            ->assertSee('Required before activation')
            ->assertSee('Preview Draft')
            ->assertSee('Discard Draft')
            ->getContent();

        $this->assertSame(1, preg_match('/id="rfc-review-missing">(.*?)<\/div>/s', $html, $missing));
        $this->assertStringContainsString('Form Revision', $missing[1]);
        $this->assertStringContainsString('Signatories', $missing[1]);
        $this->assertStringNotContainsString('Form Code', $missing[1]);
        $this->assertStringNotContainsString('Effectivity Date', $missing[1]);

        $activate = $this->openingTag($html, 'data-rfc-requires-saved');
        $this->assertTrue($this->hasFlag($activate, 'disabled'));
        $this->assertStringContainsString('data-rfc-ready="0"', $activate);

        // The server refuses regardless of what the page shows.
        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasErrors(['signatories', 'revision_label']);
        $this->assertSame('DRAFT', $draft->fresh()->status);
        $this->assertSame('ACTIVE', $this->active()->status);
    }

    public function test_read_only_revisions_show_no_save_or_draft_actions(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, ['revision_label' => 'Rev. 3']));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        // The Active revision while a Draft is in progress.
        $this->get(route('policies.report-forms.index', ['template' => $active->id]))->assertOk()
            ->assertSee('Open Working Draft')
            ->assertDontSee('data-rfc-savebar>', false)
            ->assertDontSee('<div class="rfc-savebar"', false)
            ->assertDontSee('id="rfc-review"', false)
            ->assertDontSee('Discard Draft');

        // An Archived revision.
        $this->post(route('policies.report-forms.activate', $draft))->assertSessionHasNoErrors();
        $this->get(route('policies.report-forms.index', ['template' => $active->id]))->assertOk()
            ->assertSee('Archived revision. Read-only.')
            ->assertSee('Use as New Revision')
            ->assertDontSee('<div class="rfc-savebar"', false)
            ->assertDontSee('id="rfc-review"', false)
            ->assertDontSee('Activate Version');

        // An archived revision can never be activated or discarded directly.
        $this->post(route('policies.report-forms.activate', $active))->assertStatus(422);
        $this->post(route('policies.report-forms.discard', $active))->assertStatus(422);
        $this->assertSame('ARCHIVED', $active->fresh()->status);
    }

    public function test_failed_save_on_a_draft_reopens_in_the_unsaved_state(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), ['revision_label' => 'Rev. 3']));
        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();

        $this->post(route('policies.report-forms.store'), $this->payload($draft, ['page_size' => 'A0']))
            ->assertRedirect();
        // Not assertSessionHasErrors(): it empties the flashed error bag before the next page can read it.

        $html = $this->get(route('policies.report-forms.index'))->assertOk()->getContent();
        $this->assertFalse($this->hasFlag($this->openingTag($html, 'data-rfc-savebar'), 'hidden'));
        $this->assertTrue($this->hasFlag($this->openingTag($html, 'data-rfc-review'), 'hidden'));
    }

    public function test_form_code_can_be_changed_in_draft_while_header_remains_protected(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'form_code' => 'CSPC-F-SPMU-23',
            'show_logo' => '0',
            'header_republic' => 'Tampered Republic',
            'header_institution' => 'Not CSPC',
            'header_location' => 'Unknown',
        ]))->assertSessionHasNoErrors();

        $draft = ReportFormTemplate::query()->where('status', 'DRAFT')->firstOrFail();
        $this->assertSame('CSPC-F-SPMU-23', $draft->form_code);
        $this->assertSame('CSPC-F-SPMU-22', $active->fresh()->form_code);
        $header = $draft->configuration_json['header'];
        $this->assertSame('CAMARINES SUR POLYTECHNIC COLLEGES', $header['institution']);
        $this->assertSame('Republic of the Philippines', $header['republic']);
        $this->assertTrue($header['show_logo']);
    }

    public function test_bad_form_code_is_rejected_before_creating_a_draft(): void
    {
        $this->actingAsHead();
        $this->post(route('policies.report-forms.store'), $this->payload($this->active(), [
            'form_code' => 'Invalid code <script>',
        ]))->assertSessionHasErrors('form_code');
        $this->assertSame(0, ReportFormTemplate::query()->where('status', 'DRAFT')->count());
    }

    public function test_grouped_dates_and_mandatory_control_number_are_protected_server_side(): void
    {
        $this->actingAsHead();
        $active = $this->active();
        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'column_visibility' => ['received_date' => '0', 'received_time' => '1'],
        ]))->assertSessionHasErrors('column_visibility');

        $this->post(route('policies.report-forms.store'), $this->payload($active, [
            'column_visibility' => ['control_no' => '0'],
        ]))->assertSessionHasErrors('column_visibility');
        $this->assertSame(0, ReportFormTemplate::query()->where('status', 'DRAFT')->count());
    }

    public function test_only_spmu_head_can_manage_report_forms(): void
    {
        $borrower = User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
        $this->actingAs($borrower)->withSession(['active_workspace' => 'SPMU']);

        $this->post(route('policies.report-forms.store'), $this->payload($this->active()))->assertForbidden();
        $this->post(route('policies.report-forms.discard', $this->active()))->assertForbidden();
    }
}
