<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentFormColumnAnnotation extends Model
{
    protected $fillable = [
        'table_name',
        'column_name',
        'label_en',
        'label_th',
        'ui_type',
        'sort_order',
        'col_span',
        'is_visible',
        'is_required',
        'placeholder',
        'options',
        'visibility_rules',
        'validation_rules',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'visibility_rules' => 'array',
            'validation_rules' => 'array',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'col_span' => 'integer',
        ];
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'th'
            ? ($this->label_th ?: $this->label_en ?: $this->column_name)
            : ($this->label_en ?: $this->label_th ?: $this->column_name);
    }
}
