<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateCompany;
use App\Actions\Platform\ResetOwnerPassword;
use App\Actions\Platform\SetCompanySuspension;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ResetOwnerPasswordRequest;
use App\Http\Requests\Platform\StoreCompanyRequest;
use App\Models\Company;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Support\Flash;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform panel's company pages (S10). They show company metadata only (counts, the Owner's name and email,
 * the audit trail): never sales data, issues, meetings, recordings, tasks or briefs of any company.
 */
class CompanyController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()->withCount(['users', 'departments'])->orderBy('name')->get();
        $owners = User::query()->where('role', Role::Owner->value)->whereIn('company_id', $companies->pluck('id'))
            ->orderBy('id')->get(['company_id', 'name', 'email'])->unique('company_id')->keyBy('company_id');

        return Inertia::render('platform/companies/Index', [
            'companies' => $companies->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'timezone' => $company->timezone,
                'is_demo' => $company->is_demo,
                'suspended' => $company->isSuspended(),
                'users_count' => $company->users_count,
                'departments_count' => $company->departments_count,
                'owner_email' => $owners[$company->id]->email ?? null,
                'created_at' => $company->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/companies/Create', [
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaults' => ['timezone' => (string) config('business.timezone'), 'trading_days' => [1, 2, 3, 4, 5, 6]],
        ]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $action): RedirectResponse
    {
        $company = $action->handle($request->user(), [
            'name' => $request->string('name')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'trading_days' => array_map('intval', $request->input('trading_days')),
            'branches' => $request->branchCodes(),
            'owner_name' => trim($request->string('owner_name')->toString()),
            'owner_email' => $request->string('owner_email')->toString(),
            'owner_password' => $request->string('owner_password')->toString(),
        ]);

        Flash::success("Company \"{$company->name}\" was created. Share the Owner's sign-in details with them securely.");

        return to_route('platform.companies.show', $company);
    }

    public function show(Company $company): Response
    {
        $company->loadCount(['users', 'departments']);
        $owner = User::query()->where('company_id', $company->id)->where('role', Role::Owner->value)->orderBy('id')->first(['name', 'email', 'is_active']);
        $department = $company->salesDepartment();

        return Inertia::render('platform/companies/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'timezone' => $company->timezone,
                'trading_days' => $company->trading_days,
                'is_demo' => $company->is_demo,
                'suspended' => $company->isSuspended(),
                'suspended_at' => $company->suspended_at?->toIso8601String(),
                'created_at' => $company->created_at?->toIso8601String(),
                'users_count' => $company->users_count,
                'departments_count' => $company->departments_count,
                'branches' => $department?->expectedBranches() ?? [],
            ],
            'owner' => $owner === null ? null : ['name' => $owner->name, 'email' => $owner->email, 'is_active' => $owner->is_active],
            'activity' => PlatformAuditLog::query()->with('admin:id,name')->where('company_id', $company->id)
                ->orderByDesc('id')->limit(15)->get()->map(fn (PlatformAuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'by' => $log->admin?->name,
                    'at' => $log->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function suspend(Request $request, Company $company, SetCompanySuspension $action): RedirectResponse
    {
        $action->handle($request->user(), $company, true);
        Flash::success("\"{$company->name}\" is suspended. Its users cannot sign in.");

        return back();
    }

    public function reactivate(Request $request, Company $company, SetCompanySuspension $action): RedirectResponse
    {
        $action->handle($request->user(), $company, false);
        Flash::success("\"{$company->name}\" is active again.");

        return back();
    }

    public function resetOwnerPassword(ResetOwnerPasswordRequest $request, Company $company, ResetOwnerPassword $action): RedirectResponse
    {
        $owner = $action->handle($request->user(), $company, $request->string('password')->toString());
        abort_if($owner === null, 404);

        Flash::success('The Owner password was changed and their open sessions were ended.');

        return back();
    }
}
