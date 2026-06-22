<?php

namespace App\Support;

use App\Models\DocumentFormSubmission;

/**
 * Resolve a QR-code template string against a submission, expanding
 * placeholder tokens supplied by the form designer.
 *
 * Supported tokens:
 *   {ref_no}        → submission.reference_no, or "#{id}" fallback
 *   {id}            → submission.id (always present)
 *   {url}           → absolute route to the show page
 *   {date}          → submitted date (Y-m-d) or created date for drafts
 *   {field:KEY}     → submission.payload[KEY] (arrays joined with ", ")
 *
 * Unknown tokens are left as-is so callers can include literal `{}`
 * by URL-encoding (`%7B` / `%7D`) without surprise substitution.
 */
class QrTemplateResolver
{
    public static function resolve(string $template, DocumentFormSubmission $submission): string
    {
        if ($template === '') {
            return '';
        }

        $payload = is_array($submission->payload) ? $submission->payload : [];

        $submittedAt = null;
        if (method_exists($submission, 'submittedActivity')) {
            $submittedAt = $submission->submittedActivity?->created_at;
        }
        $dateSource = $submittedAt ?? $submission->created_at;

        $tokens = [
            '{ref_no}' => (string) ($submission->reference_no ?? ('#'.$submission->id)),
            '{id}' => (string) $submission->id,
            '{url}' => route('forms.submission.show', $submission),
            '{verify_url}' => $submission->verify_token
                ? route('document.verify', ['token' => $submission->verify_token])
                : '',
            '{date}' => $dateSource ? $dateSource->format('Y-m-d') : '',
        ];
        $resolved = strtr($template, $tokens);

        // {field:KEY} substitution — separate pass because the key is dynamic.
        return preg_replace_callback('/\{field:([a-zA-Z0-9_]+)\}/', function ($m) use ($payload) {
            $val = $payload[$m[1]] ?? '';
            if (is_array($val)) {
                $val = implode(', ', array_map('strval', $val));
            }

            return (string) $val;
        }, $resolved);
    }
}
