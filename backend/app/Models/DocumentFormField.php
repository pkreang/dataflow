<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFormField extends Model
{
    use HasFactory;

    /**
     * Field types that can sensibly be filtered on from the list page.
     * Used by the admin form builder to show/hide the "searchable" toggle
     * and by the controller to reject attempts to flag non-searchable types.
     */
    public const SEARCHABLE_TYPES = [
        'text', 'textarea', 'select', 'multi_select', 'date', 'datetime',
        'number', 'email', 'phone', 'radio', 'lookup',
    ];

    protected $fillable = [
        'form_id',
        'field_key',
        'label',
        'label_en',
        'label_th',
        'field_type',
        'is_required',
        'is_searchable',
        'sort_order',
        'col_span',
        'placeholder',
        'default_value',
        'is_readonly',
        'options',
        'visible_to_departments',
        'visible_to_org_units',
        'editable_by',
        'visibility_rules',
        'required_rules',
        'required_at_step',
        'validation_rules',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_readonly' => 'boolean',
            'options' => 'array',
            'visible_to_departments' => 'array',
            'visible_to_org_units' => 'array',
            'editable_by' => 'array',
            'visibility_rules' => 'array',
            'required_rules' => 'array',
            'required_at_step' => 'array',
            'validation_rules' => 'array',
        ];
    }

    public function supportsSearch(): bool
    {
        return in_array($this->field_type, self::SEARCHABLE_TYPES, true);
    }

    /**
     * Locale-aware label for list headers / filter bars.
     * Falls back: label_{locale} → label_en → label (single-language legacy).
     */
    public function getLocalizedLabelAttribute(): string
    {
        $locale = app()->getLocale();
        $key = 'label_' . $locale;
        return (string) (
            (! empty($this->{$key}) ? $this->{$key} : null)
            ?? (! empty($this->label_en) ? $this->label_en : null)
            ?? (! empty($this->label_th) ? $this->label_th : null)
            ?? $this->label
            ?? ''
        );
    }

    /**
     * null → ['requester'] (default: requester only)
     * []   → [] (explicitly nobody — read-only to all)
     */
    public function getEffectiveEditableByAttribute(): array
    {
        return $this->editable_by ?? ['requester'];
    }

    public function form()
    {
        return $this->belongsTo(DocumentForm::class, 'form_id');
    }

    /**
     * Auto-fill label_en / label_th from the legacy single `label` when a seeder
     * or older caller only supplies one of the three. Avoids making every seeder
     * site update-aware in one go; bilingual seed values still win when present.
     */
    protected static function booted(): void
    {
        static::saving(function (self $field) {
            $label = $field->label;
            if (! empty($label)) {
                if (empty($field->label_en)) {
                    $field->label_en = $label;
                }
                if (empty($field->label_th)) {
                    $field->label_th = $label;
                }
            }
        });
    }
}
