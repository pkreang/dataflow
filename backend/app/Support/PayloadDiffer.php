<?php

namespace App\Support;

use App\Models\DocumentFormField;

/**
 * Compute a per-field diff between two form-submission payloads, suitable
 * for storing in `submission_activity_log.meta.changed_fields`.
 *
 * Output shape:
 *   [
 *     'field_key' => ['from' => mixed, 'to' => mixed],
 *     'group_key' => ['rows_added' => N, 'rows_removed' => N, 'rows_changed' => [...]],
 *     '_truncated' => true,        // when serialized output exceeds the cap
 *   ]
 *
 * Files / signatures / images: only a marker is recorded — never the raw
 * data — because base64 PNG / file paths can be huge and sensitive.
 *
 * Group repeaters: shallow row-level diff (count delta + which rows changed
 * in which inner keys). We do NOT recurse into nested groups; that's an
 * out-of-scope deferral noted in the plan.
 */
class PayloadDiffer
{
    private const FILE_LIKE_TYPES = ['file', 'multi_file', 'image', 'signature'];

    private const MAX_DIFF_BYTES = 10240; // 10 KB

    /**
     * @param  array<string, mixed>  $oldPayload
     * @param  array<string, mixed>  $newPayload
     * @param  array<string, DocumentFormField>  $fieldDefs  field_key → field model
     * @return array<string, mixed>
     */
    public static function diff(array $oldPayload, array $newPayload, array $fieldDefs = []): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldPayload), array_keys($newPayload)));

        foreach ($allKeys as $key) {
            $old = $oldPayload[$key] ?? null;
            $new = $newPayload[$key] ?? null;
            $type = $fieldDefs[$key]->field_type ?? null;

            // File-like types — never dump raw payload; emit a marker only.
            if ($type !== null && in_array($type, self::FILE_LIKE_TYPES, true)) {
                if (self::valuesDiffer($old, $new)) {
                    $changes[$key] = ['from' => self::fileMarker($old), 'to' => self::fileMarker($new)];
                }

                continue;
            }

            // Group repeater — shallow row-level diff
            if ($type === 'group') {
                $diff = self::diffGroup(is_array($old) ? $old : [], is_array($new) ? $new : []);
                if ($diff !== null) {
                    $changes[$key] = $diff;
                }

                continue;
            }

            // Scalars + arrays-as-sets (multi_select / checkbox)
            if (self::valuesDiffer($old, $new)) {
                $changes[$key] = ['from' => $old, 'to' => $new];
            }
        }

        if (empty($changes)) {
            return [];
        }

        // Cap: if serialised diff exceeds the limit, drop fields biggest-first
        // until under the limit, then add a truncation marker.
        $encoded = json_encode($changes, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) > self::MAX_DIFF_BYTES) {
            $changes = self::truncate($changes);
            $changes['_truncated'] = true;
        }

        return $changes;
    }

    /**
     * Strict-on-scalar-strings, set-equality on arrays. (string)-cast handles
     * '5' vs 5; array values are compared as unordered sets.
     */
    private static function valuesDiffer(mixed $old, mixed $new): bool
    {
        if (is_array($old) && is_array($new)) {
            sort($old);
            sort($new);

            return $old !== $new;
        }
        if ($old === null && $new === null) {
            return false;
        }

        return (string) ($old ?? '') !== (string) ($new ?? '');
    }

    private static function fileMarker(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_array($value)) {
            return 'files:'.count($value);
        }

        return 'file:set';
    }

    /**
     * @param  list<array<string, mixed>>  $old
     * @param  list<array<string, mixed>>  $new
     * @return array<string, mixed>|null null when no change
     */
    private static function diffGroup(array $old, array $new): ?array
    {
        $oldCount = count($old);
        $newCount = count($new);
        $rowsChanged = [];
        $maxScan = min($oldCount, $newCount);
        for ($i = 0; $i < $maxScan; $i++) {
            $oldRow = is_array($old[$i] ?? null) ? $old[$i] : [];
            $newRow = is_array($new[$i] ?? null) ? $new[$i] : [];
            $rowDiff = [];
            $rowKeys = array_unique(array_merge(array_keys($oldRow), array_keys($newRow)));
            foreach ($rowKeys as $rk) {
                if (self::valuesDiffer($oldRow[$rk] ?? null, $newRow[$rk] ?? null)) {
                    $rowDiff[$rk] = [
                        'from' => $oldRow[$rk] ?? null,
                        'to' => $newRow[$rk] ?? null,
                    ];
                }
            }
            if ($rowDiff) {
                $rowsChanged[] = ['idx' => $i, 'fields' => $rowDiff];
            }
        }

        $added = max(0, $newCount - $oldCount);
        $removed = max(0, $oldCount - $newCount);
        if (! $added && ! $removed && ! $rowsChanged) {
            return null;
        }

        return [
            'rows_added' => $added,
            'rows_removed' => $removed,
            'rows_changed' => $rowsChanged,
        ];
    }

    /**
     * Drop the largest-serialised entries until under the cap. Mutates a
     * copy of the input so callers don't get surprised by aliasing.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function truncate(array $changes): array
    {
        // Sort keys by their serialised size descending so we drop biggest first.
        $sizes = [];
        foreach ($changes as $k => $v) {
            $enc = json_encode($v, JSON_UNESCAPED_UNICODE);
            $sizes[$k] = $enc === false ? 0 : strlen($enc);
        }
        arsort($sizes);

        foreach (array_keys($sizes) as $key) {
            unset($changes[$key]);
            $enc = json_encode($changes, JSON_UNESCAPED_UNICODE);
            if ($enc !== false && strlen($enc) <= self::MAX_DIFF_BYTES - 100 /* leave room for _truncated marker */) {
                break;
            }
        }

        return $changes;
    }
}
