<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('settings/accounts', [
            'accounts' => User::query()->select(['id', 'school_id', 'name', 'email', 'is_active'])
                ->with('roles')->orderBy('name')->orderBy('id')->paginate(15)
                ->through(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => $user->roles->pluck('name'),
                    'can_update' => Gate::allows('update', $user),
                ]),
            'labels' => __('account_settings'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
            'school_admin' => ['required', 'boolean'],
            'school_id' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'is_active' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $teacher = new User([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            ]);
            $teacher->school_id = $request->user()->school_id;
            $teacher->is_active = true;
            $teacher->save();
            $teacher->assignRole($data['school_admin'] ? ['sped_teacher', 'school_admin'] : ['sped_teacher']);
        });

        return to_route('accounts.index');
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        Gate::authorize('update', $account);
        $data = $request->validate([
            'school_admin' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'school_id' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'remember_token' => ['prohibited'],
        ]);

        DB::transaction(function () use ($request, $account, $data) {
            // Serialize access changes within a school and recheck the actor
            // after waiting, so two administrators cannot disable each other.
            School::query()->whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $teacher = User::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $teacher);

            $teacher->syncRoles($data['school_admin'] ? ['sped_teacher', 'school_admin'] : ['sped_teacher']);
            $teacher->is_active = (bool) $data['is_active'];
            if (! $teacher->is_active) {
                $teacher->remember_token = null;
                DB::table('sessions')->where('user_id', $teacher->id)->delete();
            }
            $teacher->save();
        });

        return to_route('accounts.index');
    }
}
