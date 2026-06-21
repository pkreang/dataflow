<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait HasAutoCode
{
    abstract protected function autoCodePrefix(): string;

    protected static function bootHasAutoCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->auto_code)) {
                $model->auto_code = $model->generateAutoCode();
            }
        });
    }

    public function generateAutoCode(): string
    {
        $prefix = $this->autoCodePrefix();
        $table = $this->getTable();

        return DB::transaction(function () use ($prefix, $table) {
            $rows = DB::table($table)
                ->where('auto_code', 'like', $prefix.'-%')
                ->lockForUpdate()
                ->pluck('auto_code');

            $max = $rows->reduce(function ($carry, $code) use ($prefix) {
                $n = (int) substr((string) $code, strlen($prefix) + 1);

                return max($carry, $n);
            }, 0);

            return $prefix.'-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
        });
    }
}
