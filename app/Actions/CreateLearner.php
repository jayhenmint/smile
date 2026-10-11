<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\School;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateLearner
{
    public function execute(User $actor, array $data): bool
    {
        return DB::transaction(function () use ($actor, $data) {
            School::query()->whereKey($actor->school_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewAny', Learner::class);
            $section = Section::query()->where('adviser_id', $actor->id)
                ->whereKey($data['section_id'])->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [Learner::class, $section]);
            Gate::forUser($actor)->authorize('create', [Enrollment::class, $section]);

            // Global uniqueness check discloses only availability. Keep the query
            // inside the controller's sanitized failure boundary (no SQL bindings).
            if (! empty($data['lrn']) && DB::table('learners')->where('lrn', $data['lrn'])->exists()) {
                throw ValidationException::withMessages(['lrn' => __('learners.lrn_unavailable')]);
            }

            // School-wide duplicate signal only; never disclose the matching record.
            $duplicate = empty($data['lrn']) && DB::table('learners')
                ->where('school_id', $actor->school_id)
                ->whereRaw('lower(first_name) = ?', [mb_strtolower($data['first_name'])])
                ->whereRaw('lower(last_name) = ?', [mb_strtolower($data['last_name'])])
                ->where('birthdate', $data['birthdate'])->exists();

            $learner = new Learner(collect($data)->only([
                'first_name', 'last_name', 'middle_name', 'birthdate', 'sex',
                'address', 'mother_tongue', 'lrn', 'lrn_status', 'health_notes',
            ])->all());
            $learner->school_id = $actor->school_id;
            $learner->consent_confirmed_by = $actor->id;
            $learner->consent_confirmed_at = now();
            $learner->save();

            $enrollment = new Enrollment(collect($data)->only(['enrolled_on', 'is_transferee', 'previous_school'])->all());
            $enrollment->school_id = $actor->school_id;
            $enrollment->learner_id = $learner->id;
            $enrollment->school_year_id = $section->school_year_id;
            $enrollment->section_id = $section->id;
            $enrollment->level_status = 'non_graded';
            $enrollment->save();
            app(AuditLearner::class)->record($actor, $learner, 'created');

            return $duplicate;
        });
    }
}
