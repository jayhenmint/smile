<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['full_name', 'relationship', 'phone', 'email', 'address'];

    protected $hidden = ['user_id'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope('learner_access', fn (Builder $query) => $query->whereHas('links'));
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer'];
    }

    public function links(): HasMany
    {
        return $this->hasMany(LearnerGuardian::class);
    }
}
