<?php

namespace App\Models;

use App\Models\Scopes\LearnerRecordScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerDisability extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'diagnosis_date', 'assessed_by', 'notes'];

    protected $hidden = ['diagnosis_date', 'assessed_by', 'notes'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new LearnerRecordScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'category_id' => 'integer', 'diagnosis_date' => 'encrypted', 'assessed_by' => 'encrypted', 'notes' => 'encrypted'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DisabilityCategory::class, 'category_id');
    }
}
