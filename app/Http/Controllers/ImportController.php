<?php

namespace App\Http\Controllers;

use App\Actions\Imports\CancelImport;
use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Actions\Imports\RetryImport;
use App\Enums\ImportStatus;
use App\Http\Requests\UploadImportRequest;
use App\Models\Department;
use App\Models\Import;
use App\Models\ImportError;
use App\Services\CurrentCompany;
use App\Services\Imports\CsvImportReader;
use App\Services\Imports\ExpectedBranches;
use App\Services\Imports\ImportActionException;
use App\Services\Imports\ImportPreviewBuilder;
use App\Services\Imports\ImportRejectedException;
use App\Services\Imports\ReportingCoverage;
use App\Support\Flash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Data Imports pages and actions. Every action authorizes through ImportPolicy; imports are resolved through
 * Import::visibleTo (404 outside the user's department). There is no delete or edit action, and the stored
 * file path is never sent to the frontend.
 */
class ImportController extends Controller
{
    public function index(Request $request, ReportingCoverage $coverage): Response
    {
        Gate::authorize('viewAny', Import::class);
        $user = $request->user();

        $status = $request->query('status');
        $status = is_string($status) && in_array($status, ImportStatus::values(), true) ? $status : null;

        $imports = Import::visibleTo($user)
            ->with('uploader:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate((int) config('imports.imports_per_page'))
            ->withQueryString();

        $timezone = app(CurrentCompany::class)->resolve()?->timezone ?? config('business.timezone');
        $department = $this->userDepartment($request);

        return Inertia::render('imports/Index', [
            'imports' => $imports->getCollection()->map(fn (Import $import) => $this->summary($import, $timezone))->values(),
            'pagination' => $this->pagination($imports),
            'filter' => ['status' => $status],
            'statuses' => collect(ImportStatus::cases())->map(fn (ImportStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'hasProcessing' => Import::visibleTo($user)->where('status', ImportStatus::Processing->value)->exists(),
            'freshness' => $department === null ? null : $this->freshness($coverage, $department),
        ]);
    }

    public function create(Request $request, CsvImportReader $reader, ExpectedBranches $expectedBranches): Response
    {
        Gate::authorize('create', Import::class);

        $department = $this->userDepartment($request);

        return Inertia::render('imports/Upload', [
            'header' => $reader->templateHeader(),
            'limits' => [
                'max_megabytes' => config('imports.max_file_bytes') / 1048576,
                'max_rows' => config('imports.max_rows'),
            ],
            'expectedBranches' => $department === null ? [] : $expectedBranches->get($department),
        ]);
    }

    public function store(UploadImportRequest $request, CreateImportFromUpload $create): RedirectResponse
    {
        try {
            $import = $create->handle($request->user(), $request->file('file'));
        } catch (ImportRejectedException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $import);
    }

    public function show(Request $request, Import $import, ImportPreviewBuilder $previews): Response
    {
        Gate::authorize('view', $import);

        $import->loadMissing(['uploader:id,name', 'department', 'company']);
        $timezone = $import->company->timezone;

        $preview = null;
        $previewError = null;
        $weeks = [];

        if ($import->status === ImportStatus::Validated) {
            try {
                $preview = $previews->build($import)->toArray();
            } catch (Throwable) {
                $previewError = 'The source file is unavailable, so the preview cannot be shown.';
            }
        }

        if ($import->status->isFinished()) {
            $weeks = $previews->weeksOf($import);
        }

        $errors = ImportError::query()
            ->where('import_id', $import->id)
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate((int) config('imports.errors_per_page'), pageName: 'errors_page')
            ->withQueryString();

        return Inertia::render('imports/Show', [
            'record' => $this->detail($import, $timezone),
            'preview' => $preview,
            'previewError' => $previewError,
            'weeks' => $weeks,
            // Not named `errors`: Inertia reserves that prop for validation errors.
            'rowErrors' => [
                'data' => $errors->getCollection()->map(fn (ImportError $error) => [
                    'id' => $error->id,
                    'row_number' => $error->row_number,
                    'column' => $error->column,
                    'code' => $error->code,
                    'message' => $error->message,
                    'raw_row' => $error->raw_row,
                ])->values(),
                'pagination' => $this->pagination($errors),
            ],
            'actions' => [
                'confirm' => $import->status === ImportStatus::Validated && $import->valid_rows > 0,
                'cancel' => in_array($import->status, [ImportStatus::Uploaded, ImportStatus::Validated], true),
                'retry' => $import->status === ImportStatus::Failed,
            ],
        ]);
    }

    public function confirm(Import $import, ConfirmImport $action): RedirectResponse
    {
        Gate::authorize('confirm', $import);

        try {
            $before = $import->status;
            $current = $action->handle($import);
        } catch (ImportActionException $e) {
            Flash::error($e->getMessage());

            return redirect()->route('imports.show', $import);
        }

        // A repeated confirm (double click) returns the current import; nothing is dispatched twice.
        $current->status === ImportStatus::Processing && $before === ImportStatus::Validated
            ? Flash::success('Import confirmed. Processing has started.')
            : Flash::toast('info', 'This import was already confirmed.');

        return redirect()->route('imports.show', $import);
    }

    public function cancel(Import $import, CancelImport $action): RedirectResponse
    {
        Gate::authorize('cancel', $import);

        try {
            $action->handle($import);
            Flash::success('Import cancelled. No sales data was changed.');
        } catch (ImportActionException $e) {
            Flash::error($e->getMessage());
        }

        return redirect()->route('imports.show', $import);
    }

    public function retry(Import $import, RetryImport $action): RedirectResponse
    {
        Gate::authorize('retry', $import);

        try {
            $action->handle($import);
            Flash::success('Retrying the import.');
        } catch (ImportActionException $e) {
            Flash::error($e->getMessage());
        }

        return redirect()->route('imports.show', $import);
    }

    /**
     * The department the signed-in user imports into: the Owner's company department, or the Manager's own.
     */
    private function userDepartment(Request $request): ?Department
    {
        $user = $request->user();

        return $user->isOwner()
            ? Department::query()->where('company_id', $user->company_id)->orderBy('id')->first()
            : Department::query()->find($user->department_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function freshness(ReportingCoverage $coverage, Department $department): ?array
    {
        if ($coverage->expectedBranches($department) === []) {
            return ['configured' => false];
        }

        $freshness = $coverage->freshness($department);

        return [
            'configured' => true,
            'state' => $freshness->state->value,
            'latest_sales_date' => $freshness->latestSalesDate?->format('Y-m-d'),
            'last_due_date' => $freshness->lastDueDate->format('Y-m-d'),
            'overdue_branch_days' => $freshness->overdueBranchDays,
            'overdue_from' => $freshness->overdueFrom?->format('Y-m-d'),
            'overdue_to' => $freshness->overdueTo?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Import $import, string $timezone): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'original_filename' => $import->original_filename,
            'min_date' => $import->min_date?->format('Y-m-d'),
            'max_date' => $import->max_date?->format('Y-m-d'),
            'total_rows' => $import->total_rows,
            'invalid_rows' => $import->invalid_rows,
            'uploader' => $import->uploader?->name,
            'uploaded_at' => $import->created_at?->setTimezone($timezone)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Import $import, string $timezone): array
    {
        $size = null;
        if ($import->stored_path !== '' && Storage::disk('local')->exists($import->stored_path)) {
            $size = Storage::disk('local')->size($import->stored_path);
        }

        $local = fn ($time) => $time?->setTimezone($timezone)->toIso8601String();

        return [
            ...$this->summary($import, $timezone),
            'file_size' => $size,
            'file_hash' => $import->file_hash,
            'valid_rows' => $import->valid_rows,
            'inserted_rows' => $import->inserted_rows,
            'corrected_rows' => $import->corrected_rows,
            'unchanged_rows' => $import->unchanged_rows,
            'confirmed_at' => $local($import->confirmed_at),
            'completed_at' => $local($import->completed_at),
            'error_message' => $import->error_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'prev_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
        ];
    }
}
