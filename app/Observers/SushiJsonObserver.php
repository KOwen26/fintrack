<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Mirrors Sushi-backed model changes back to their JSON source file.
 *
 * The model must use the HasSushiJsonSource trait (which exposes
 * jsonSourcePath() and jsonColumns()). On every save the row is upserted by
 * its primary key (slug); on delete the row is removed. Only the columns
 * declared by jsonColumns() are written, so the Sushi-internal id never
 * leaks into the source file.
 */
class SushiJsonObserver
{
    public function saved(Model $model): void
    {
        if (! $this->appliesTo($model)) {
            return;
        }

        $this->writeRow($model);
    }

    public function deleted(Model $model): void
    {
        if (! $this->appliesTo($model)) {
            return;
        }

        $this->removeRow($model);
    }

    private function writeRow(Model $model): void
    {
        $path = $model->jsonSourcePath();
        $key = $model->getKeyName();
        $keyValue = $model->getKey();
        $originalKey = method_exists($model, 'getOriginal')
            ? $model->getOriginal($key)
            : $keyValue;

        $rows = $this->read($path);

        // If the natural key changed, drop the stale row before re-inserting.
        if ($originalKey !== null && $originalKey !== $keyValue) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row[$key] ?? null) !== $originalKey
            ));
        }

        $row = [$key => $keyValue];
        foreach ($model->jsonColumns() as $column) {
            $row[$column] = $model->{$column} ?? null;
        }

        $updated = false;
        foreach ($rows as &$existing) {
            if (($existing[$key] ?? null) === $keyValue) {
                // Merge so a partial update never clobbers columns the model
                // does not currently carry. Update is the primary operation,
                // so preserving untouched columns matters most here.
                foreach ($model->jsonColumns() as $column) {
                    $existing[$column] = $model->{$column} ?? $existing[$column] ?? null;
                }

                $updated = true;
                break;
            }
        }

        if (! $updated) {
            $rows[] = $row;
        }

        $this->persist($path, $rows);
    }

    private function removeRow(Model $model): void
    {
        $path = $model->jsonSourcePath();
        $key = $model->getKeyName();
        $keyValue = $model->getKey();

        $rows = $this->read($path);

        $next = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row[$key] ?? null) !== $keyValue
        ));

        if (count($next) === count($rows)) {
            return;
        }

        $this->persist($path, $next);
    }

    private function appliesTo(Model $model): bool
    {
        return method_exists($model, 'jsonSourcePath')
            && method_exists($model, 'jsonColumns');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true);

        return $rows ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persist(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));

        File::put(
            $path,
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    }
}
