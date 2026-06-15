<?php

namespace App\Models;

use App\Enums\HouseholdMemberRole;
use Database\Factories\HouseholdMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    /** @use HasFactory<HouseholdMemberFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'role' => HouseholdMemberRole::class,
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
