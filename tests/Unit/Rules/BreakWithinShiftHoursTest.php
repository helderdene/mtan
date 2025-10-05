<?php

use App\Rules\BreakWithinShiftHours;

describe('BreakWithinShiftHours Rule', function () {
    describe('Standard Shifts (start_time < end_time)', function () {
        test('passes when break is within standard shift hours', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;

            $rule->validate('break_start', '10:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break_start equals shift start_time', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;

            $rule->validate('break_start', '09:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break_end equals shift end_time', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;

            $rule->validate('break_end', '17:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('fails when break is before shift start', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_start', '08:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break times must fall within shift working hours');
        });

        test('fails when break is after shift end', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_end', '18:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Break times must fall within shift working hours');
        });

        test('passes when break is in middle of shift', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;

            $rule->validate('break_start', '12:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });
    });

    describe('Overnight Shifts (end_time < start_time)', function () {
        test('passes when break is within overnight shift hours (before midnight)', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;

            $rule->validate('break_start', '23:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break is within overnight shift hours (after midnight)', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;

            $rule->validate('break_end', '02:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break_start equals overnight shift start_time', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;

            $rule->validate('break_start', '22:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when break_end equals overnight shift end_time', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;

            $rule->validate('break_end', '06:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('fails when break is outside overnight shift hours', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;
            $errorMessage = '';

            // 10:00:00 is not within 22:00:00-23:59:59 or 00:00:00-06:00:00
            $rule->validate('break_start', '10:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('overnight');
        });

        test('fails when break is in the gap between shift end and start', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;
            $errorMessage = '';

            // 12:00:00 is in the gap (06:00:01 - 21:59:59)
            $rule->validate('break_start', '12:00:00', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('overnight');
        });
    });

    describe('Edge Cases', function () {
        test('passes when break time is null', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;

            $rule->validate('break_start', null, function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when shift times are null', function () {
            $rule = new BreakWithinShiftHours(null, null);
            $passes = true;

            $rule->validate('break_start', '12:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('passes when both break and shift times are null', function () {
            $rule = new BreakWithinShiftHours(null, null);
            $passes = true;

            $rule->validate('break_start', null, function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });

        test('fails with invalid time format', function () {
            $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
            $passes = true;
            $errorMessage = '';

            $rule->validate('break_start', 'invalid-time', function ($message) use (&$passes, &$errorMessage) {
                $passes = false;
                $errorMessage = $message;
            });

            expect($passes)->toBeFalse();
            expect($errorMessage)->toContain('Invalid time format');
        });

        test('handles midnight correctly for overnight shifts', function () {
            $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
            $passes = true;

            $rule->validate('break_start', '00:00:00', function ($message) use (&$passes) {
                $passes = false;
            });

            expect($passes)->toBeTrue();
        });
    });
});
