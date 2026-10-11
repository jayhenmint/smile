<?php

use App\Http\Requests\SaveLearnerDocumentRequest;
use App\Http\Requests\StoreAssessmentRecordRequest;
use App\Http\Requests\StoreLearnerRequest;
use App\Http\Requests\UpdateLearnerRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

test('school date validation accepts the Philippine day before UTC midnight and rejects tomorrow', function (string $requestClass, string $field, array $input) {
    $this->travelTo(Carbon::parse('2026-10-11 17:00:00', 'UTC'));
    expect(now()->toDateString())->toBe('2026-10-11');

    $request = $requestClass::create('/', 'POST', $input);
    $actor = new User;
    $actor->school_id = 1;
    $actor->id = '00000000-0000-4000-8000-000000000001';
    $request->setUserResolver(fn () => $actor);
    $rules = [$field => $request->rules()[$field]];

    expect(Validator::make([$field => '2026-10-12'], $rules)->passes())->toBeTrue();
    expect(Validator::make([$field => '2026-10-13'], $rules)->passes())->toBeFalse();
})->with([
    'learner creation birthdate' => [StoreLearnerRequest::class, 'birthdate', []],
    'learner correction birthdate' => [UpdateLearnerRequest::class, 'birthdate', []],
    'document submission' => [SaveLearnerDocumentRequest::class, 'submitted_on', ['status' => 'submitted']],
    'teacher assessment' => [StoreAssessmentRecordRequest::class, 'assessed_on', ['kind' => 'teacher']],
    'diagnosis date' => [StoreAssessmentRecordRequest::class, 'diagnosis_date', ['kind' => 'disability']],
]);
