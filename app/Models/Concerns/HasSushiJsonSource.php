<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Shared JSON-source configuration for Sushi-backed models.
 *
 * The using model must also `use Sushi` and bridge Sushi's hooks to the
 * distinct methods below. Sushi itself defines getRows/sushiShouldCache/
 * sushiCacheReferencePath, so we avoid the name clash by using unique names
 * here and delegating from the model's own (class) methods, which override
 * Sushi's trait methods without any `insteadof` resolution.
 *
 * Reads come from Sushi's cached SQLite; writes go through normal Eloquent
 * create/save/delete and are mirrored back to the JSON file by
 * SushiJsonObserver, which reads jsonSourcePath()/jsonColumns().
 */
trait HasSushiJsonSource
{
    /**
     * Absolute path to the JSON file that backs this model.
     */
    abstract public function jsonSourcePath(): string;

    /**
     * Columns that belong in the JSON file. The Sushi-internal id column is
     * intentionally excluded so it is never written back to the source.
     *
     * @return array<int, string>
     */
    abstract public function jsonColumns(): array;

    public function getJsonRows(): array
    {
        $path = $this->jsonSourcePath();

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true);

        return $rows ?? [];
    }

    protected function jsonShouldCache(): bool
    {
        return true;
    }

    protected function jsonCacheReferencePath(): ?string
    {
        return $this->jsonSourcePath();
    }

    /**
     * Use a builder that re-syncs the JSON source after bulk update()/delete()
     * operations, which bypass Eloquent model events (and thus the observer).
     */
    public function newModelQuery()
    {
        return new SushiJsonBuilder($this->newBaseQueryBuilder())->setModel($this);
    }
}
