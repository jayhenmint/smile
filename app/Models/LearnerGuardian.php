<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LearnerGuardian extends Pivot
{
    protected $table = 'learner_guardians';

    public $timestamps = false;

    protected $fillable = ['is_primary', 'is_emergency'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope('learner_access', fn (Builder $query) => $query->whereHas('learner'));
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'is_primary' => 'boolean', 'is_emergency' => 'boolean'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
