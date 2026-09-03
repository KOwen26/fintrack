<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

/**
 * Query builder for Sushi-backed models that mirror writes back to their
 * JSON source file.
 *
 * Eloquent model events (saved/deleted) do not fire for bulk query-builder
 * operations such as `Model::where(...)->update([...])` or `->delete()`, so
 * the SushiJsonObserver cannot catch them. This builder wraps update() and
 * delete() to re-sync the entire JSON source from the model's SQLite table
 * after the statement executes, covering both model-level and bulk writes.
 *
 * insert() is intentionally NOT wrapped: Sushi uses static::insert() while
 * populating its cache table during migration, and re-syncing there would
 * write the JSON source on every cold boot.
 */
class SushiJsonBuilder extends Builder
{
    public function update(array $values)
    {
        $affected = parent::update($values);

        $this->syncJsonSource();

        return $affected;
    }

    public function delete()
    {
        $affected = parent::delete();

        $this->syncJsonSource();

        return $affected;
    }

    protected function syncJsonSource(): void
    {
        $model = $this->model;

        if ($model === null
            || ! method_exists($model, 'jsonSourcePath')
            || ! method_exists($model, 'jsonColumns')) {
            return;
        }

        $rows = $model->newQuery()->get()->map(function ($row) use ($model) {
            $data = [];

            foreach ($model->jsonColumns() as $column) {
                $data[$column] = $row->{$column};
            }

            return $data;
        })->all();

        File::ensureDirectoryExists(dirname($model->jsonSourcePath()));

        File::put(
            $model->jsonSourcePath(),
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    }
}
