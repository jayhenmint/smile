<?php

use App\Http\Controllers\LearnerGuardianController;
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
    Route::get('learners/{learner}/guardians', [LearnerGuardianController::class, 'index'])->whereUuid('learner')->name('learners.guardians.index');
    Route::post('learners/{learner}/guardians', [LearnerGuardianController::class, 'store'])->whereUuid('learner')->name('learners.guardians.store');
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
