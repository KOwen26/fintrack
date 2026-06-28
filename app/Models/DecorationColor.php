<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Sushi\Sushi;

class DecorationColor extends Model
{
    use Sushi;

    public function getRows(): array
    {
        $path = resource_path('js/data/decoration-colors.json');

        return json_decode(file_get_contents($path), true) ?? [];
    }

    protected function afterMigrate(Blueprint $table)
    {
        $table->index('slug');
    }
}
