<?php

namespace Tests\Feature;

use App\Models\DocumentFormField;
use App\Support\PayloadDiffer;
use Tests\TestCase;

class PayloadDifferTest extends TestCase
{
    public function test_scalar_change_records_from_to(): void
    {
        $diff = PayloadDiffer::diff(['amount' => 5000], ['amount' => 50000]);
        $this->assertSame([
            'amount' => ['from' => 5000, 'to' => 50000],
        ], $diff);
    }

    public function test_unchanged_keys_omitted(): void
    {
        $diff = PayloadDiffer::diff(
            ['title' => 'X', 'amount' => 100],
            ['title' => 'X', 'amount' => 200],
        );
        $this->assertArrayNotHasKey('title', $diff);
        $this->assertArrayHasKey('amount', $diff);
    }

    public function test_string_vs_int_treated_as_equal(): void
    {
        // '5' vs 5 should not register as a change (DB roundtrip artefact)
        $diff = PayloadDiffer::diff(['n' => '5'], ['n' => 5]);
        $this->assertSame([], $diff);
    }

    public function test_array_value_treated_as_set(): void
    {
        // multi_select / checkbox arrays are unordered
        $same = PayloadDiffer::diff(
            ['hazards' => ['fire', 'chemical']],
            ['hazards' => ['chemical', 'fire']],
        );
        $this->assertSame([], $same);

        $changed = PayloadDiffer::diff(
            ['hazards' => ['fire']],
            ['hazards' => ['fire', 'chemical']],
        );
        $this->assertArrayHasKey('hazards', $changed);
    }

    public function test_file_field_summarised_as_marker(): void
    {
        $field = $this->makeFieldDef('photo', 'image');
        $diff = PayloadDiffer::diff(
            ['photo' => '/storage/old.png'],
            ['photo' => '/storage/new.png'],
            ['photo' => $field],
        );
        $this->assertArrayHasKey('photo', $diff);
        $this->assertSame('file:set', $diff['photo']['from']);
        $this->assertSame('file:set', $diff['photo']['to']);
        // Raw paths must NOT leak into the diff
        $this->assertStringNotContainsString('storage/old.png', json_encode($diff));
    }

    public function test_signature_field_summarised_as_marker(): void
    {
        $field = $this->makeFieldDef('sig', 'signature');
        $diff = PayloadDiffer::diff(
            ['sig' => 'data:image/png;base64,A...'],
            ['sig' => 'data:image/png;base64,B...'],
            ['sig' => $field],
        );
        $this->assertSame('file:set', $diff['sig']['from']);
    }

    public function test_multi_file_records_count(): void
    {
        $field = $this->makeFieldDef('attachments', 'multi_file');
        $diff = PayloadDiffer::diff(
            ['attachments' => ['/a.pdf']],
            ['attachments' => ['/a.pdf', '/b.pdf', '/c.pdf']],
            ['attachments' => $field],
        );
        $this->assertSame('files:1', $diff['attachments']['from']);
        $this->assertSame('files:3', $diff['attachments']['to']);
    }

    public function test_group_field_records_row_count_delta(): void
    {
        $field = $this->makeFieldDef('beneficiaries', 'group');
        $diff = PayloadDiffer::diff(
            ['beneficiaries' => [['name' => 'A', 'pct' => 100]]],
            ['beneficiaries' => [
                ['name' => 'A', 'pct' => 60],
                ['name' => 'B', 'pct' => 40],
            ]],
            ['beneficiaries' => $field],
        );
        $g = $diff['beneficiaries'];
        $this->assertSame(1, $g['rows_added']);
        $this->assertSame(0, $g['rows_removed']);
        $this->assertCount(1, $g['rows_changed']);   // row 0 changed pct 100→60
        $this->assertSame(0, $g['rows_changed'][0]['idx']);
        $this->assertSame(100, $g['rows_changed'][0]['fields']['pct']['from']);
        $this->assertSame(60, $g['rows_changed'][0]['fields']['pct']['to']);
    }

    public function test_group_field_unchanged_returns_empty(): void
    {
        $field = $this->makeFieldDef('rows', 'group');
        $diff = PayloadDiffer::diff(
            ['rows' => [['x' => 1]]],
            ['rows' => [['x' => 1]]],
            ['rows' => $field],
        );
        $this->assertSame([], $diff);
    }

    public function test_truncation_marker_when_diff_exceeds_10kb(): void
    {
        // Build a payload with one field carrying a very long value (~12 KB).
        $bigText = str_repeat('Q', 12000);
        $diff = PayloadDiffer::diff(
            ['notes' => 'short'],
            ['notes' => $bigText],
        );
        $this->assertTrue($diff['_truncated'] ?? false, 'expected _truncated marker');
        // The oversized field should be dropped from the diff
        $this->assertArrayNotHasKey('notes', $diff);
    }

    public function test_null_to_value_is_a_change(): void
    {
        $diff = PayloadDiffer::diff([], ['title' => 'New']);
        $this->assertSame(['title' => ['from' => null, 'to' => 'New']], $diff);
    }

    public function test_value_to_null_is_a_change(): void
    {
        $diff = PayloadDiffer::diff(['title' => 'Old'], ['title' => null]);
        $this->assertSame(['title' => ['from' => 'Old', 'to' => null]], $diff);
    }

    private function makeFieldDef(string $key, string $type): DocumentFormField
    {
        // Hydrate without persisting — the differ only reads $field->field_type
        $field = new DocumentFormField;
        $field->field_key = $key;
        $field->field_type = $type;

        return $field;
    }
}
