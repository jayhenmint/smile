<?php

namespace App\Models;

use App\Models\Scopes\AdviserSectionScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Section extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'placement_type', 'adviser_id'];

    protected $attributes = ['grade_level' => 'Non-graded', 'placement_type' => 'sped_self_contained'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new AdviserSectionScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'school_year_id' => 'integer'];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'section_teachers')->using(SectionTeacher::class)
            ->withPivot(['school_id', 'role'])->wherePivot('school_id', $this->school_id);
    }
}
