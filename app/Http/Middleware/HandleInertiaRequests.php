<?php

namespace App\Http\Middleware;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $school = $request->user()?->school;

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'schoolSettingsNav' => [
                'visible' => $school !== null && Gate::allows('update', $school),
                'label' => __('school_settings.title'),
            ],
            'accountSettingsNav' => [
                'visible' => Gate::allows('viewAny', User::class),
                'label' => __('account_settings.title'),
            ],
            'schoolYearSettingsNav' => [
                'visible' => Gate::allows('viewAny', SchoolYear::class),
                'label' => __('school_year_settings.title'),
            ],
            'sectionSettingsNav' => [
                'visible' => Gate::allows('viewAny', Section::class),
                'label' => __('section_settings.title'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
        ]);
    }
}
