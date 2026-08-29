<?php

declare(strict_types=1);

use App\Models\DecorationColor;
use App\Models\DecorationIcon;
use App\Models\Traits\HasSushiJsonSource;
use App\Observers\SushiJsonObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Sushi\Sushi;
use Tests\TestCase;

class SushiTestModel extends Model
{
    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public string $sourcePath = '';

    public array $sourceColumns = [];

    public function jsonSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function jsonColumns(): array
    {
        return $this->sourceColumns;
    }
}

/**
 * A real Sushi-backed model (uses the Sushi trait + HasSushiJsonSource) so we
 * can exercise the bulk query-builder path that bypasses model events.
 */
class SushiBulkTestModel extends Model
{
    use HasSushiJsonSource;
    use Sushi;

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static string $sourcePath = '';

    public function getRows(): array
    {
        return $this->getJsonRows();
    }

    protected function sushiShouldCache(): bool
    {
        return $this->jsonShouldCache();
    }

    protected function sushiCacheReferencePath(): ?string
    {
        return $this->jsonCacheReferencePath();
    }

    public function jsonSourcePath(): string
    {
        return static::$sourcePath;
    }

    public function jsonColumns(): array
    {
        return ['slug', 'label', 'status'];
    }
}

uses(TestCase::class)->group('unit');

beforeEach(function (): void {
    $this->path = sys_get_temp_dir() . '/sushi_obs_' . uniqid() . '.json';
    File::put($this->path, json_encode([
        ['slug' => 'a', 'label' => 'A', 'status' => 'Active'],
    ], JSON_PRETTY_PRINT));
});

afterEach(function (): void {
    File::delete($this->path);
});

test('saved upserts a new row by slug', function (): void {
    $model = new SushiTestModel([
        'slug' => 'b', 'label' => 'B', 'status' => 'Active',
    ]);
    $model->sourcePath = $this->path;
    $model->sourceColumns = ['slug', 'label', 'status'];

    (new SushiJsonObserver)->saved($model);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(2);
    expect($rows[1])->toBe(['slug' => 'b', 'label' => 'B', 'status' => 'Active']);
    expect(array_keys($rows[1]))->not->toContain('id');
});

test('saved updates an existing row by slug', function (): void {
    $model = new SushiTestModel([
        'slug' => 'a', 'label' => 'Updated', 'status' => 'Active',
    ]);
    $model->sourcePath = $this->path;
    $model->sourceColumns = ['slug', 'label', 'status'];

    (new SushiJsonObserver)->saved($model);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(1);
    expect($rows[0]['label'])->toBe('Updated');
});

test('saved merges a partial update without clobbering other columns', function (): void {
    // Update is the primary operation: a model carrying only some columns
    // must not null out the columns it does not currently hold.
    $model = new SushiTestModel([
        'slug' => 'a', 'label' => 'Updated',
    ]);
    $model->sourcePath = $this->path;
    $model->sourceColumns = ['slug', 'label', 'status'];

    (new SushiJsonObserver)->saved($model);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(1);
    expect($rows[0]['label'])->toBe('Updated');
    expect($rows[0]['status'])->toBe('Active'); // preserved from original row
});

test('saved moves the row when the natural key changes', function (): void {
    $model = new SushiTestModel([
        'slug' => 'a', 'label' => 'A', 'status' => 'Active',
    ]);
    $model->syncOriginal();
    $model->slug = 'b';
    $model->sourcePath = $this->path;
    $model->sourceColumns = ['slug', 'label', 'status'];

    (new SushiJsonObserver)->saved($model);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(1);
    expect($rows[0]['slug'])->toBe('b');
    expect(array_column($rows, 'slug'))->not->toContain('a');
});

test('deleted removes the row by slug', function (): void {
    $model = new SushiTestModel([
        'slug' => 'a', 'label' => 'A', 'status' => 'Active',
    ]);
    $model->sourcePath = $this->path;
    $model->sourceColumns = ['slug', 'label', 'status'];

    (new SushiJsonObserver)->deleted($model);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(0);
});

test('observer ignores models without the json source contract', function (): void {
    $plain = new class extends Model {};

    (new SushiJsonObserver)->saved($plain);

    $rows = json_decode(File::get($this->path), true);
    expect($rows)->toHaveCount(1);
});

test('decoration models are observed by SushiJsonObserver', function (): void {
    foreach ([DecorationColor::class, DecorationIcon::class] as $model) {
        $attributes = (new ReflectionClass($model))->getAttributes(ObservedBy::class);
        expect($attributes)->toHaveCount(1);

        $observed = $attributes[0]->getArguments()[0] ?? [];
        expect($observed)->toContain(SushiJsonObserver::class);
    }
});

test('decoration models keep independent sushi connections', function (): void {
    // Both models use Sushi directly (per-leaf), so each must resolve its own
    // connection. Querying both in the same process must not clobber each other
    // (regression guard for the shared $sushiConnection static bug).
    // Clear any stale Sushi cache so we read fresh from the JSON sources.
    collect(glob(storage_path('framework/cache/sushi-*')))->each(
        static fn (string $file): bool => file_exists($file) && unlink($file)
    );

    expect(DecorationColor::where('slug', 'slate-50')->first()->label)->toBe('Slate 50');
    expect(DecorationIcon::where('slug', 't-shirt-bold')->first()->label)->toBe('T-Shirt');
});

test('bulk query builder update syncs the json source', function (): void {
    // Bulk updates bypass Eloquent model events, so the observer alone would
    // miss them. The custom SushiJsonBuilder must re-sync the JSON file.
    $path = sys_get_temp_dir() . '/sushi_bulk_' . uniqid() . '.json';
    File::put($path, json_encode([
        ['slug' => 'a', 'label' => 'A', 'status' => 'Active'],
        ['slug' => 'b', 'label' => 'B', 'status' => 'Active'],
    ], JSON_PRETTY_PRINT));

    SushiBulkTestModel::$sourcePath = $path;

    SushiBulkTestModel::where('slug', 'a')->update(['label' => 'A-UPDATED']);

    $rows = json_decode(File::get($path), true);
    expect($rows[0]['label'])->toBe('A-UPDATED');
    expect($rows[1]['label'])->toBe('B');

    File::delete($path);
});

test('bulk query builder delete syncs the json source', function (): void {
    $path = sys_get_temp_dir() . '/sushi_bulk_del_' . uniqid() . '.json';
    File::put($path, json_encode([
        ['slug' => 'a', 'label' => 'A', 'status' => 'Active'],
        ['slug' => 'b', 'label' => 'B', 'status' => 'Active'],
    ], JSON_PRETTY_PRINT));

    SushiBulkTestModel::$sourcePath = $path;

    SushiBulkTestModel::where('slug', 'a')->delete();

    $rows = json_decode(File::get($path), true);
    expect($rows)->toHaveCount(1);
    expect(array_column($rows, 'slug'))->toBe(['b']);

    File::delete($path);
});
