<?php

use App\Models\Budget;
use App\Support\BudgetCycle;
use Carbon\Carbon;

test('cycle range spans cutoff to cutoff when now is before the cutoff', function () {
    Carbon::setTestNow('2026-08-20 12:00:00');

    $budget = new Budget(['cutoff_day' => 25]);

    [$start, $end] = BudgetCycle::currentCycleRange($budget);

    expect($start->toDateString())->toBe('2026-07-26')
        ->and($end->toDateString())->toBe('2026-08-25');
});

test('cycle range spans cutoff to next cutoff when now is after the cutoff', function () {
    Carbon::setTestNow('2026-08-27 12:00:00');

    $budget = new Budget(['cutoff_day' => 25]);

    [$start, $end] = BudgetCycle::currentCycleRange($budget);

    expect($start->toDateString())->toBe('2026-08-26')
        ->and($end->toDateString())->toBe('2026-09-25');
});

test('cutoff day is clamped to the last day of a short month', function () {
    // April 2026 has 30 days; cutoff 31 must clamp to 30.
    Carbon::setTestNow('2026-04-20 12:00:00');

    [$start, $end] = BudgetCycle::currentCycleRange(new Budget(['cutoff_day' => 31]));

    expect($start->toDateString())->toBe('2026-04-01')
        ->and($end->toDateString())->toBe('2026-04-30');
});

test('no budget falls back to the calendar month', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    [$start, $end] = BudgetCycle::currentCycleRange(null);

    expect($start->toDateString())->toBe('2026-08-01')
        ->and($end->toDateString())->toBe('2026-08-31');
});

test('exact cutoff day belongs to the current cycle (inclusive end)', function () {
    Carbon::setTestNow('2026-08-25 09:00:00');

    [$start, $end] = BudgetCycle::currentCycleRange(new Budget(['cutoff_day' => 25]));

    expect($start->toDateString())->toBe('2026-07-26')
        ->and($end->toDateString())->toBe('2026-08-25');
});
