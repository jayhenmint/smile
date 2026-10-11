<?php

namespace App\Models;

use App\Actions\ReserveSmileId;
use App\Models\Scopes\AdvisedLearnerScope;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Learner extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['first_name', 'last_name', 'middle_name', 'birthdate', 'sex', 'address', 'mother_tongue', 'lrn', 'lrn_status', 'health_notes'];

    protected $hidden = ['health_notes', 'pwd_id_number', 'photo_url'];

    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);
        static::addGlobalScope(new AdvisedLearnerScope);
        static::creating(fn (Learner $learner) => $learner->smile_id = app(ReserveSmileId::class)->reserve());
        static::updating(function (Learner $learner) {
            if ($learner->isDirty('smile_id')) {
                throw new LogicException('A learner SMILE ID cannot be changed.');
            }
        });
    }

    protected function casts(): array
    {
        return ['school_id' => 'integer', 'birthdate' => 'date:Y-m-d', 'health_notes' => 'encrypted',
            'has_pwd_id' => 'boolean', 'consent_confirmed_at' => 'datetime'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function guardianLinks(): HasMany
    {
        return $this->hasMany(LearnerGuardian::class);
    }
}
