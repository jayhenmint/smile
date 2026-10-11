<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SectionTeacher extends Pivot
{
    protected $table = 'section_teachers';

    public $timestamps = false;

    protected $fillable = ['section_id', 'user_id', 'role'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope('visible_section', fn (Builder $query) => $query->whereHas('section'));
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
