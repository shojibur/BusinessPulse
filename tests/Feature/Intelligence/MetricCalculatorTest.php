<?php

use App\Models\SalesRecord;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->m = new MetricCalculator);

test('percentages are computed at 4 decimals and displayed at 2, rounding half up', function () {
    expect($this->m->weeklyChangePct('124000.00', '155000.00'))->toBe('-20.0000')
        ->and($this->m->display2('-20.0000'))->toBe('-20.00')
        ->and($this->m->targetAchievementPct('124000.00', '156000.00'))->toBe('79.4872')   // 79.487179...
        ->and($this->m->display2('79.4872'))->toBe('79.49')
        ->and($this->m->weeklyChangePct('126000.00', '155000.00'))->toBe('-18.7097')
        ->and($this->m->display2('-18.7097'))->toBe('-18.71')
        ->and($this->m->targetAchievementPct('126000.00', '156000.00'))->toBe('80.7692')
        ->and($this->m->weeklyChangePct('155000.00', '154000.00'))->toBe('0.6494')
        ->and($this->m->coveragePct(17, 18))->toBe('94.4444')
        ->and($this->m->display2('94.4444'))->toBe('94.44');
});

test('rounding is half up and symmetric, and never produces negative zero', function () {
    expect($this->m->roundHalfUp('0.12345', 4))->toBe('0.1235')
        ->and($this->m->roundHalfUp('-0.12345', 4))->toBe('-0.1235')
        ->and($this->m->roundHalfUp('2.675', 2))->toBe('2.68')      // would be 2.67 with float rounding
        ->and($this->m->roundHalfUp('-0.00001', 4))->toBe('0.0000')
        ->and($this->m->roundHalfUp('-0.00004', 4))->toBe('0.0000')
        ->and($this->m->roundHalfUp('-0.00005', 4))->toBe('-0.0001')
        ->and($this->m->weeklyChangePct('100.00', '100.00'))->toBe('0.0000');
});

test('a zero denominator returns the indicator, never a number or null', function () {
    expect($this->m->weeklyChangePct('10.00', '0.00'))->toBe(Indeterminate::ZeroDenominator)
        ->and($this->m->targetAchievementPct('10.00', '0'))->toBe(Indeterminate::ZeroDenominator)
        ->and($this->m->coveragePct(0, 0))->toBe(Indeterminate::ZeroDenominator)
        ->and($this->m->targetAchievementPct('0.00', '100.00'))->toBe('0.0000'); // zero revenue is a real value
});

test('the calculator is deterministic and works on decimal strings only', function () {
    foreach (range(1, 3) as $ignored) {
        expect($this->m->targetAchievementPct('124000.00', '156000.00'))->toBe('79.4872');
    }

    // No float anywhere: a value that floats cannot represent exactly stays exact.
    expect($this->m->pct('0.10', '0.30'))->toBe('33.3333')
        ->and($this->m->weeklyChangePct('1000000000000.01', '1000000000000.00'))->toBe('0.0000');

    $source = file_get_contents(app_path('Services/Intelligence/MetricCalculator.php'));
    expect($source)->not->toContain('(float)')->not->toContain('floatval')->not->toContain('number_format(')->not->toContain(' round(');
});

test('money is formatted from strings with thousands separators', function () {
    expect($this->m->formatMoney('124000.00'))->toBe('RM 124,000.00')
        ->and($this->m->formatMoney('999.5'))->toBe('RM 999.50')
        ->and($this->m->formatMoney('-31000'))->toBe('-RM 31,000.00')
        ->and($this->m->formatMoney('1234567.89'))->toBe('RM 1,234,567.89');
});

test('weekly totals are exact sums over active records only', function () {
    $f = identity();
    $import = importCsv($f->owner, weekCsv('2026-09-14', '0.10'));

    $totals = $this->m->weekTotals($f->sales->id, CarbonImmutable::parse('2026-09-14', 'UTC'));

    // 18 x 0.10 would be 1.7999999999999998 in floating point.
    expect($totals->revenue)->toBe('1.80')->and($totals->target)->toBe('90000.00')
        ->and($totals->recordIds)->toHaveCount(18)->and($totals->importIds)->toBe([$import->id]);

    // A superseded row no longer counts.
    $row = SalesRecord::where('is_active', true)->first();
    $row->update(['is_active' => false, 'superseded_by_import_id' => $import->id, 'superseded_at' => now()]);
    expect($this->m->weekTotals($f->sales->id, CarbonImmutable::parse('2026-09-14', 'UTC'))->recordIds)->toHaveCount(17);
});
