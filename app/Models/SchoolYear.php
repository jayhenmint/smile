<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolYear extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['label', 'start_date', 'end_date', 'is_current'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
