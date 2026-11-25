<?php

use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BriefAudioController;
use App\Http\Controllers\BriefController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\IssueTransitionController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingMediaController;
use App\Http\Controllers\MeetingProcessingController;
use App\Http\Controllers\MeetingRecordingController;
use App\Http\Controllers\MeetingTranscriptController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RefreshIntelligenceController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDraftController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/today')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

// Every application route requires a signed-in, active user. `section.access` returns 403 for navigation
// sections the role does not see; policies authorize each record.
Route::middleware(['auth', 'active', 'section.access'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('today', DashboardController::class)->name('today');
    // Issues (S04). Owner and Manager only: section.access plus IssuePolicy on every action.
    Route::get('issues', [IssueController::class, 'index'])->name('issues.index');
    Route::post('issues/refresh', RefreshIntelligenceController::class)->middleware('throttle:6,1')->name('issues.refresh');
    Route::get('issues/{issue}', [IssueController::class, 'show'])->name('issues.show');
    Route::post('issues/{issue}/acknowledge', [IssueTransitionController::class, 'acknowledge'])->name('issues.acknowledge');
    Route::post('issues/{issue}/monitoring', [IssueTransitionController::class, 'startMonitoring'])->name('issues.monitoring');
    Route::post('issues/{issue}/resolve', [IssueTransitionController::class, 'resolve'])->name('issues.resolve');

    // Notifications: every signed-in user sees and clears only their own.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    // Data Imports (S03). Owner and Manager only: section.access plus ImportPolicy on every action.
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/create', [ImportController::class, 'create'])->name('imports.create');
    Route::get('imports/template', ImportTemplateController::class)->name('imports.template');
    Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:imports-upload')->name('imports.store');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('imports/{import}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
    Route::post('imports/{import}/retry', [ImportController::class, 'retry'])->name('imports.retry');
    // Executive briefs (S05). Owner and Manager only: section.access plus BriefPolicy on every action.
    Route::get('briefs', [BriefController::class, 'index'])->name('briefs.index');
    Route::post('briefs', [BriefController::class, 'store'])->middleware('throttle:ai-requests')->name('briefs.store');
    Route::get('briefs/{brief}', [BriefController::class, 'show'])->name('briefs.show');
    Route::post('briefs/{brief}/retry', [BriefController::class, 'retry'])->middleware('throttle:ai-requests')->name('briefs.retry');
    Route::post('briefs/{brief}/audio', [BriefAudioController::class, 'store'])->middleware('throttle:ai-requests')->name('briefs.audio.store');
    Route::get('briefs/{brief}/audio', [AudioStreamController::class, 'show'])->middleware('throttle:audio-stream')->name('briefs.audio.stream');
    // S01 placeholder pages: later specs replace each with a controller.
    // Meetings (S06). Owner and Manager only: section.access, department-scoped route binding (Staff 403, out of scope 404)
    // and MeetingPolicy on every action.
    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('meetings', [MeetingController::class, 'store'])->middleware('throttle:meetings-actions')->name('meetings.store');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
    Route::put('meetings/{meeting}', [MeetingController::class, 'update'])->middleware('throttle:meetings-actions')->name('meetings.update');
    Route::post('meetings/{meeting}/recording', [MeetingRecordingController::class, 'store'])->middleware('throttle:meetings-upload')->name('meetings.recording.store');
    Route::get('meetings/{meeting}/recording/stream', [MeetingMediaController::class, 'stream'])->middleware('throttle:meetings-media')->name('meetings.recording.stream');
    Route::get('meetings/{meeting}/recording/download', [MeetingMediaController::class, 'download'])->middleware('throttle:meetings-media')->name('meetings.recording.download');
    Route::post('meetings/{meeting}/transcript', [MeetingTranscriptController::class, 'store'])->middleware('throttle:meetings-actions')->name('meetings.transcript.store');
    Route::put('meetings/{meeting}/transcript', [MeetingTranscriptController::class, 'update'])->middleware('throttle:meetings-actions')->name('meetings.transcript.update');
    Route::post('meetings/{meeting}/retry', [MeetingProcessingController::class, 'retry'])->middleware('throttle:meetings-actions')->name('meetings.retry');
    Route::post('meetings/{meeting}/re-extract', [MeetingProcessingController::class, 'reExtract'])->middleware('throttle:meetings-actions')->name('meetings.re-extract');
    // Tasks (S07). Management list (Owner, Manager) and every user's own tasks; route binding scopes records (foreign or
    // missing ids are the same 404; Staff get 403 on drafts), policies authorize every action.
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('my-tasks', [TaskController::class, 'mine'])->name('tasks.mine');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('tasks/{task}/status', TaskStatusController::class)->middleware('throttle:task-status')->name('tasks.status');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('throttle:task-comment')->name('tasks.comments.store');
    Route::patch('task-drafts/{taskDraft}', [TaskDraftController::class, 'update'])->middleware('throttle:task-review')->name('task-drafts.update');
    Route::post('task-drafts/{taskDraft}/approve', [TaskDraftController::class, 'approve'])->middleware('throttle:task-review')->name('task-drafts.approve');
    Route::post('task-drafts/{taskDraft}/reject', [TaskDraftController::class, 'reject'])->middleware('throttle:task-review')->name('task-drafts.reject');
    Route::inertia('outcomes', 'outcomes/Index')->name('outcomes.index');

    Route::redirect('settings', '/settings/company')->name('settings.index');
    Route::get('settings/company', [CompanyController::class, 'edit'])->name('settings.company');
    Route::get('settings/staff', [UserController::class, 'index'])->name('settings.staff');
    Route::get('settings/department/{department?}', [DepartmentController::class, 'show'])->name('settings.department');
});

// Unmatched URLs (any method) run through the web middleware so the 404 page knows who is signed in.
// Removed starter-kit routes such as /register therefore answer 404, never 405.
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', fn () => abort(404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
