<?php

namespace App\Models;

use App\Models\Scopes\LearnerRecordScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssessment extends Model
{
    public $timestamps = false;

    protected $fillable = ['assessed_on', 'observations'];

    protected $hidden = ['observations'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new LearnerRecordScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'assessed_on' => 'date:Y-m-d', 'observations' => 'encrypted'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
