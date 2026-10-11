<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['deped_school_id', 'name', 'division', 'region', 'address'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope('id', schoolMetadata: true));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function schoolYears(): HasMany
    {
        return $this->hasMany(SchoolYear::class);
    }
}
