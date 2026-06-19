<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class DocumentForm extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'form_key',
        'name',
        'document_type',
        'description',
        'is_active',
        'evaluation_enabled',
        'target_document_types',
        'layout_columns',
        'submission_table',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'evaluation_enabled' => 'boolean',
            'target_document_types' => 'array',
        ];
    }

    public function fields()
    {
        return $this->hasMany(DocumentFormField::class, 'form_id')->orderBy('sort_order');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'document_form_departments', 'form_id', 'department_id');
    }

    public function orgUnits(): BelongsToMany
    {
        return $this->belongsToMany(OrgUnit::class, 'document_form_org_units', 'form_id', 'org_unit_id');
    }

    /**
     * Filter forms visible to a user — Phase 2b: org_unit-first, department fallback.
     * Form เห็นได้เมื่อ (ก) ไม่มี restriction ทั้ง org_unit และ department, หรือ
     * (ข) org_unit ของ user อยู่ใน orgUnits ของ form, หรือ (ค) department ตรง (fallback).
     * เซ็ต org config ว่าง → ลดรูปเป็นพฤติกรรม department เดิม (non-breaking).
     */
    public function scopeVisibleToUser(Builder $query, ?int $orgUnitId, ?int $departmentId = null): Builder
    {
        return $query->where(function ($q) use ($orgUnitId, $departmentId) {
            $q->where(function ($noRestrict) {
                $noRestrict->whereDoesntHave('orgUnits')->whereDoesntHave('departments');
            });
            if ($orgUnitId !== null) {
                $q->orWhereHas('orgUnits', fn ($oq) => $oq->where('org_units.id', $orgUnitId));
            }
            if ($departmentId !== null) {
                $q->orWhereHas('departments', fn ($dq) => $dq->where('departments.id', $departmentId));
            }
        });
    }

    public function workflowPolicies()
    {
        return $this->hasMany(DocumentFormWorkflowPolicy::class, 'form_id');
    }

    public function hasDedicatedTable(): bool
    {
        return $this->submission_table !== null;
    }

    protected static function booted(): void
    {
        static::saved(function (DocumentForm $form) {
            \App\Support\DataSourceRegistry::flushFormSourcesCache();
            static::syncNavigationMenu($form);
            Cache::forget('navigation_menus_tree');
        });
        static::deleted(function () {
            \App\Support\DataSourceRegistry::flushFormSourcesCache();
            // FK cascade removes navigation_menus rows but does not fire their
            // model events — refresh sidebar tree cache directly.
            Cache::forget('navigation_menus_tree');
        });
    }

    /**
     * Mirror the form into navigation_menus. Split create vs update so admins can
     * freely rename / reorder / swap icons / move under a different parent in
     * Menu Manager without the observer wiping their work on the next form save.
     *
     * Fields we always sync on update (tied to the form's identity):
     *   route       — follows form_key; user clicking the menu must actually reach the form
     *   is_active   — inactive form ⇒ hidden menu row (protects against dead links)
     *
     * Fields we only set on CREATE (admin may customize and we respect that):
     *   label/label_en/label_th, icon, parent_id, sort_order
     */
    public static function syncNavigationMenu(DocumentForm $form): void
    {
        $nav = \App\Models\NavigationMenu::where('document_form_id', $form->id)->first();
        $route = '/forms/'.$form->form_key.'/submissions';

        if ($nav) {
            $nav->update([
                'route' => $route,
                'is_active' => (bool) $form->is_active,
            ]);

            return;
        }

        \App\Models\NavigationMenu::create([
            'document_form_id' => $form->id,
            'parent_id' => 48,
            'label' => $form->name,
            'label_en' => $form->name,
            'label_th' => $form->name,
            'icon' => 'document-text',
            'route' => $route,
            'permission' => null,
            'is_active' => (bool) $form->is_active,
        ]);
    }

    protected function autoCodePrefix(): string
    {
        return 'FORM';
    }
}
