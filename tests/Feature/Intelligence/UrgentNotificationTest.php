<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Actions\Intelligence\SeedDefaultRules;
use App\Events\Intelligence\UrgentIssueNotified;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Notifications\UrgentIssueNotification;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\UrgentIssueNotifier;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

function strongDecline(): void
{
    setClock('2026-09-28 09:00');
    importCsv(test()->owner, weekCsv('2026-09-14', '1000.00', '1000.00'));
    importCsv(test()->owner, weekCsv('2026-09-21', '600.00', '600.00')); // -40%: high, target met
}

test('a high issue notifies the company Owner and the active Managers of its department, nobody else', function () {
    Event::fake([UrgentIssueNotified::class]);
    strongDecline();

    $issue = Issue::where('title', 'Weekly revenue decline')->firstOrFail();
    $recipients = DatabaseNotification::pluck('notifiable_id')->sort()->values()->all();

    expect($recipients)->toBe(collect([$this->owner->id, $this->f->manager->id])->sort()->values()->all())
        ->and(DatabaseNotification::pluck('notifiable_id')->contains($this->f->staff->id))->toBeFalse()
        ->and(DatabaseNotification::pluck('notifiable_id')->contains($this->f->otherManager->id))->toBeFalse() // another department
        ->and(DatabaseNotification::first()->type)->toBe(UrgentIssueNotification::class);

    $data = DatabaseNotification::first()->data;
    expect(array_keys($data))->toEqualCanonicalizing(['dedupe_key', 'issue_id', 'title', 'severity', 'department_name', 'summary_line', 'url'])
        ->and($data)->toMatchArray(['issue_id' => $issue->id, 'title' => 'Weekly revenue decline', 'severity' => 'high', 'dedupe_key' => "{$issue->id}:high", 'url' => "/issues/{$issue->id}"]);

    Event::assertDispatched(UrgentIssueNotified::class, fn (UrgentIssueNotified $e) => $e->issueId === $issue->id && $e->dedupeKey === "{$issue->id}:high"
        && collect($e->recipientUserIds)->sort()->values()->all() === $recipients);
});

test('re-detection of a high issue does not notify again', function () {
    strongDecline();
    expect(DatabaseNotification::count())->toBe(2);

    setClock('2026-09-29 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    expect(Issue::where('title', 'Weekly revenue decline')->first()->detection_count)->toBe(2)->and(DatabaseNotification::count())->toBe(2);
});

test('a medium issue creates no urgent notification', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00', '1000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '800.00', '800.00')); // -20% (medium, below high 25), target met

    expect(Issue::where('title', 'Weekly revenue decline')->first()->severity->value)->toBe('medium')->and(DatabaseNotification::count())->toBe(0);
});

test('escalation to high notifies once and the issue_id:severity key prevents duplicates', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00', '1000.00'));
    BusinessRule::query()->get();
    app(SeedDefaultRules::class)->handle($this->f->sales);
    BusinessRule::where('key', 'weekly_revenue_decline')->update(['params' => ['high_drop_pct' => '90.00']]);
    importCsv($this->owner, weekCsv('2026-09-21', '700.00', '700.00'));

    $issue = Issue::where('title', 'Weekly revenue decline')->firstOrFail();
    expect($issue->severity->value)->toBe('medium')->and(DatabaseNotification::count())->toBe(0);

    BusinessRule::where('key', 'weekly_revenue_decline')->update(['params' => ['high_drop_pct' => '25.00']]);
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    expect($issue->fresh()->severity->value)->toBe('high')->and(DatabaseNotification::count())->toBe(2);

    // Notifying the same issue and severity again is a no-op.
    $evaluation = $issue->fresh()->latestEvaluation;
    app(UrgentIssueNotifier::class)->maybeNotify($issue->fresh(), $evaluation);
    expect(DatabaseNotification::count())->toBe(2);
});

test('a high recurrence issue notifies again under its own key', function () {
    strongDecline();
    $first = Issue::where('title', 'Weekly revenue decline')->firstOrFail();
    app(IssueLifecycle::class)->resolveManual($first, $this->owner, 'Handled.');

    setClock('2026-09-29 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    $second = Issue::where('title', 'Weekly revenue decline')->where('status', '!=', 'resolved')->firstOrFail();
    $keys = DatabaseNotification::get()->pluck('data.dedupe_key')->unique()->sort()->values()->all();

    expect($second->id)->not->toBe($first->id)->and(DatabaseNotification::count())->toBe(4)
        ->and($keys)->toBe(collect(["{$first->id}:high", "{$second->id}:high"])->sort()->values()->all());
});

test('inactive users receive nothing', function () {
    $this->f->manager->forceFill(['is_active' => false])->save();
    strongDecline();

    expect(DatabaseNotification::pluck('notifiable_id')->all())->toBe([$this->owner->id]);
});

test('no email, sms or push is ever sent', function () {
    Mail::fake();
    Notification::fake();
    strongDecline();

    Mail::assertNothingSent();
    Notification::assertSentTo($this->owner, UrgentIssueNotification::class, fn ($n, $channels) => $channels === ['database']);
});

test('the summary line is built from the stored evaluation', function () {
    strongDecline();

    expect(DatabaseNotification::first()->data['summary_line'])->toBe('Revenue fell 40.00% (RM 10,800.00 vs RM 18,000.00)');
});
