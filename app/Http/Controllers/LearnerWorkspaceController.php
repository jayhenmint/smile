<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LearnerWorkspaceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewLearnerWorkspace', User::class);

        // Layout only. No learner records are queried or supplied at this stage.
        return Inertia::render('learners/index');
    }
}
