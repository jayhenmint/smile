<?php

use App\Http\Controllers\LearnerAssessmentController;
use App\Http\Controllers\LearnerDocumentController;
use App\Http\Controllers\LearnerDocumentFileController;
use App\Http\Controllers\LearnerEnrollmentController;
use App\Http\Controllers\LearnerGuardianController;
use App\Http\Controllers\LearnerLrnController;
use App\Http\Controllers\LearnerProfileController;
use App\Http\Controllers\LearnerWorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('learners', [LearnerWorkspaceController::class, 'index'])->name('learners.index');
    Route::get('learners/create', [LearnerWorkspaceController::class, 'create'])->name('learners.create');
    Route::post('learners', [LearnerWorkspaceController::class, 'store'])->name('learners.store');
    Route::get('learners/{learner}', [LearnerProfileController::class, 'show'])->whereUuid('learner')->name('learners.show');
    Route::get('learners/{learner}/enrollments/create', [LearnerEnrollmentController::class, 'create'])->whereUuid('learner')->name('learners.enrollments.create');
    Route::post('learners/{learner}/enrollments', [LearnerEnrollmentController::class, 'store'])->whereUuid('learner')->name('learners.enrollments.store');
    Route::post('learners/{learner}/lrn', [LearnerLrnController::class, 'store'])->whereUuid('learner')->name('learners.lrn.store');
    Route::get('learners/{learner}/edit', [LearnerProfileController::class, 'edit'])->whereUuid('learner')->name('learners.edit');
    Route::patch('learners/{learner}', [LearnerProfileController::class, 'update'])->whereUuid('learner')->name('learners.update');
    Route::get('learners/{learner}/guardians', [LearnerGuardianController::class, 'index'])->whereUuid('learner')->name('learners.guardians.index');
    Route::post('learners/{learner}/guardians', [LearnerGuardianController::class, 'store'])->whereUuid('learner')->name('learners.guardians.store');
    Route::patch('learners/{learner}/guardians/{guardian}', [LearnerGuardianController::class, 'update'])->whereUuid(['learner', 'guardian'])->name('learners.guardians.update');
    Route::post('learners/{learner}/documents/files/{document}', [LearnerDocumentFileController::class, 'store'])->whereUuid('learner')->whereNumber('document')->name('learners.documents.files.store');
    Route::get('learners/{learner}/documents/files/{document}', [LearnerDocumentFileController::class, 'download'])->whereUuid('learner')->whereNumber('document')->middleware('signed')->name('learners.documents.files.download');
    Route::get('learners/{learner}/documents', [LearnerDocumentController::class, 'index'])->whereUuid('learner')->name('learners.documents.index');
    Route::put('learners/{learner}/documents/{documentType}', [LearnerDocumentController::class, 'save'])->whereUuid('learner')->whereNumber('documentType')->name('learners.documents.save');
    Route::get('learners/{learner}/assessments', [LearnerAssessmentController::class, 'index'])->whereUuid('learner')->name('learners.assessments.index');
    Route::post('learners/{learner}/assessments', [LearnerAssessmentController::class, 'store'])->whereUuid('learner')->name('learners.assessments.store');
    Route::patch('learners/{learner}/assessments/teacher/{assessment}', [LearnerAssessmentController::class, 'updateTeacher'])->whereUuid('learner')->whereNumber('assessment')->name('learners.assessments.teacher.update');
    Route::patch('learners/{learner}/assessments/disability/{disability}', [LearnerAssessmentController::class, 'updateDisability'])->whereUuid('learner')->whereNumber('disability')->name('learners.assessments.disability.update');
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
