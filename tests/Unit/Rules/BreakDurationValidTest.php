<?php

use App\Rules\BreakDurationValid;

describe('BreakDurationValid Rule', function () {
    describe('Valid Durations', function () {
        test('passes when break duration is exactly 1 minute', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;

            $rule->validate('break_end', '12:01:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break duration is 30 minutes', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;

            $rule->validate('break_end', '12:30:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break duration is 1 hour', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;

            $rule->validate('break_end', '13:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break duration is exactly 2 hours (maximum)', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;

            $rule->validate('break_end', '14:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break duration is 90 minutes', function () {
            $rule = new BreakDurationValid('10:30:00');
            $passes = true;

            $rule->validate('break_end', '12:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });
    });

    describe('Invalid Durations - Too Short', function () {
        test('fails when break duration is 0 minutes (same time)', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '12:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break duration must be at least 1 minute');
        });

        test('fails when break_end is before break_start (negative duration)', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '11:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break duration must be at least 1 minute');
        });
    });

    describe('Invalid Durations - Too Long', function () {
        test('fails when break duration exceeds 2 hours', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '15:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break duration cannot exceed 2 hours');
        });

        test('fails when break duration is 3 hours', function () {
            $rule = new BreakDurationValid('10:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '13:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break duration cannot exceed 2 hours');
        });

        test('fails when break duration is 2 hours and 1 minute', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '14:01:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break duration cannot exceed 2 hours');
        });
    });

    describe('Edge Cases', function () {
        test('passes when break_start is null', function () {
            $rule = new BreakDurationValid(null);
            $passes = true;

            $rule->validate('break_end', '12:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break_end is null', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;

            $rule->validate('break_end', null, function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when both break times are null', function () {
            $rule = new BreakDurationValid(null);
            $passes = true;

            $rule->validate('break_end', null, function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('fails with invalid time format for break_start', function () {
            $rule = new BreakDurationValid('invalid-time');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '12:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Invalid time format');
        });

        test('fails with invalid time format for break_end', function () {
            $rule = new BreakDurationValid('12:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', 'invalid-time', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Invalid time format');
        });

        test('handles seconds in time format correctly', function () {
            $rule = new BreakDurationValid('12:00:30');
            $passes = true;

            $rule->validate('break_end', '12:30:30', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });
    });
});
