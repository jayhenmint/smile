<?php

namespace App\Models;

use App\Models\Scopes\AdvisedEnrollmentScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    public $timestamps = false;

    protected $fillable = ['enrolled_on', 'is_transferee', 'previous_school'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new AdvisedEnrollmentScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'school_year_id' => 'integer', 'section_id' => 'integer', 'enrolled_on' => 'date:Y-m-d', 'is_transferee' => 'boolean'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
