<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateCompanyUser;
use App\Actions\Platform\ResetUserPassword;
use App\Actions\Platform\SetUserActive;
use App\Actions\Platform\UpdateCompanyUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CompanyUserRequest;
use App\Http\Requests\Platform\ResetUserPasswordRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A platform admin's management of a company's users (S10). Routes use scoped bindings, so a user of another company
 * is a 404. Users are never deleted: they are deactivated, and their history stays.
 */
class CompanyUserController extends Controller
{
    public function store(CompanyUserRequest $request, Company $company, CreateCompanyUser $action): RedirectResponse
    {
        $user = $action->handle($request->user(), $company, $request->validated());
        Flash::success("{$user->name} was added. Share the sign-in details with them securely.");

        return to_route('platform.companies.show', $company);
    }

    public function update(CompanyUserRequest $request, Company $company, User $user, UpdateCompanyUser $action): RedirectResponse
    {
        $action->handle($request->user(), $company, $user, $request->validated());
        Flash::success("{$user->name} was updated.");

        return to_route('platform.companies.show', $company);
    }

    public function deactivate(Request $request, Company $company, User $user, SetUserActive $action): RedirectResponse
    {
        $action->handle($request->user(), $company, $user, false);
        Flash::success("{$user->name} is deactivated and signed out.");

        return back();
    }

    public function reactivate(Request $request, Company $company, User $user, SetUserActive $action): RedirectResponse
    {
        $action->handle($request->user(), $company, $user, true);
        Flash::success("{$user->name} is active again.");

        return back();
    }

    public function password(ResetUserPasswordRequest $request, Company $company, User $user, ResetUserPassword $action): RedirectResponse
    {
        $action->handle($request->user(), $company, $user, $request->string('password')->toString());
        Flash::success("{$user->name}'s password was changed and their open sessions were ended.");

        return back();
    }
}
