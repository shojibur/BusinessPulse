<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\BusinessClock;
use App\Support\Navigation;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // The signed-in platform admin (S10), name and email only; null on every company page.
            'platformAdmin' => fn () => ($admin = Auth::guard('platform')->user()) === null ? null : ['name' => $admin->name, 'email' => $admin->email],
            'navigation' => fn () => Navigation::items($request),
            // Today's business date (the demo clock in demo mode), for the header.
            'businessDate' => fn () => $user === null ? null : app(BusinessClock::class)->today()->format('j M Y'),
            'notifications' => fn () => $user === null ? null : $this->notifications($user),
            'flash' => fn () => $request->session()->get('flash'),
        ];
    }

    /**
     * The bell: unread count and the latest notifications of THIS user only.
     *
     * @return array{unread_count: int, items: list<array<string, mixed>>}
     */
    private function notifications(User $user): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => NotificationPresenter::many($user, $user->notifications()->limit(5)->get()),
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
