<?php

require_once __DIR__.'/Outcomes/helpers.php';

beforeEach(function () {
    $this->x = monitoringScenario();
    importFixture($this->x->owner, 'week9-interim', '2026-10-05 09:00');
    $this->outcome = latestOutcome($this->x->decline);
});

test('the outcome detail page carries the issue detection date and severity for its header, and defers the activity trace', function () {
    $this->actingAs($this->x->owner)->get("/outcomes/{$this->outcome->id}")->assertOk()->assertInertia(fn ($p) => $p
        ->component('outcomes/Show')
        ->where('outcome.issue.status', $this->x->decline->status->value)
        ->where('outcome.issue.severity', $this->x->decline->severity->value)
        ->where('outcome.issue.detectedLabel', fn ($label) => is_string($label) && $label !== '')
        ->missing('trace'));
});

test('the deferred activity trace of the outcome page is the issue trace and never leaks passwords or paths', function () {
    $version = $this->actingAs($this->x->owner)->get("/outcomes/{$this->outcome->id}")->viewData('page')['version'];
    $response = $this->actingAs($this->x->owner)->get("/outcomes/{$this->outcome->id}", ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Inertia-Partial-Component' => 'outcomes/Show', 'X-Inertia-Partial-Data' => 'trace']);

    $response->assertOk();
    expect($response->json('props.trace.stages'))->toBeArray()->not->toBeEmpty()
        ->and($response->getContent())->not->toContain('$2y$')->not->toContain('stored_path');
});
