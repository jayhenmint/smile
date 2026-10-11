<?php

namespace App\Models;

use App\Models\Scopes\LearnerRecordScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['status', 'submitted_on', 'notes'];

    protected $hidden = ['notes', 'file_url'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new LearnerRecordScope);
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'submitted_on' => 'date:Y-m-d', 'notes' => 'encrypted'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
