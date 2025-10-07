<?php

use App\Models\Tenant\StrangerLog;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('StrangerLog Model Unit Tests', function () {
    test('model has correct fillable attributes', function () {
        $model = new StrangerLog();
        $fillable = $model->getFillable();

        expect($fillable)->toContain('device_id')
            ->and($fillable)->toContain('employee_id')
            ->and($fillable)->toContain('matched_by')
            ->and($fillable)->toContain('detected_at')
            ->and($fillable)->toContain('photo_path')
            ->and($fillable)->toContain('match_status')
            ->and($fillable)->toContain('notes');
    });

    test('model casts detected_at to datetime', function () {
        $model = new StrangerLog();
        $casts = $model->getCasts();

        expect($casts['detected_at'])->toBe('datetime');
    });

    test('model has device relationship method', function () {
        $model = new StrangerLog();

        expect(method_exists($model, 'device'))->toBeTrue();
    });

    test('model has employee relationship method', function () {
        $model = new StrangerLog();

        expect(method_exists($model, 'employee'))->toBeTrue();
    });

    test('model has matchedBy relationship method', function () {
        $model = new StrangerLog();

        expect(method_exists($model, 'matchedBy'))->toBeTrue();
    });
});
