<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Every field is selected explicitly. The user model is never serialized wholesale, and no list of
     * other users, password, remember token, manager id, session data or secret is shared.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : $this->authUser($user),
                'role' => $user?->role->value,
            ],
            'company' => fn () => $user === null ? null : $this->company(),
            'navigation' => fn () => Navigation::items($request),
            'flash' => fn () => $request->session()->get('flash'),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, job_title: string|null, department: array{id: int, name: string}|null}
     */
    private function authUser(User $user): array
    {
        // Load the full relation: a partial select would leave a half-built Department on the user instance.
        $user->loadMissing('department');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'job_title' => $user->job_title,
            'department' => $user->department === null
                ? null
                : ['id' => $user->department->id, 'name' => $user->department->name],
        ];
    }

    /**
     * @return array{name: string, timezone: string, currency: string, trading_days: list<int>}|null
     */
    private function company(): ?array
    {
        $company = app(CurrentCompany::class)->resolve();

        return $company === null ? null : [
            'name' => $company->name,
            'timezone' => $company->timezone,
            'currency' => $company->currency,
            'trading_days' => $company->trading_days,
        ];
    }
}
