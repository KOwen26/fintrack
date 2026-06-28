<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class DecorationIcon extends Model
{
    use Sushi;

    public function getRows(): array
    {
        $path = resource_path('js/data/decoration-icons.json');

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
