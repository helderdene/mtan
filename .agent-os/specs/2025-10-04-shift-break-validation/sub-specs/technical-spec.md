# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-04-shift-break-validation/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Technical Requirements

### 1. Validation Rules

The system shall implement the following validation rules for shift break times:

#### Rule 1: Break Completeness
- **Constraint**: If `break_start` is provided, `break_end` must also be provided, and vice versa
- **Rationale**: Prevents partial break configurations that would cause processing errors
- **Error Message**: "Both break start and end times are required. Please provide both or leave both empty."

#### Rule 2: Break Time Order
- **Constraint**: `break_start` must be strictly before `break_end`
- **Rationale**: Logical time flow requirement
- **Error Message**: "Break start time must be before break end time."

#### Rule 3: Minimum Break Duration
- **Constraint**: Break duration must be at least 1 minute
- **Rationale**: Zero-duration breaks are meaningless and likely input errors
- **Error Message**: "Break duration must be at least 1 minute."

#### Rule 4: Maximum Break Duration
- **Constraint**: Break duration must not exceed 2 hours (configurable)
- **Rationale**: Prevents accidentally creating unrealistic break periods
- **Error Message**: "Break duration cannot exceed 2 hours. Please verify your break times."

#### Rule 5: Break Within Shift Hours (Standard Shifts)
- **Constraint**: For standard shifts (where `end_time` > `start_time`):
  - `break_start` >= `start_time`
  - `break_end` <= `end_time`
- **Rationale**: Breaks must occur during working hours
- **Error Message**: "Break times must fall within shift working hours ({start_time} - {end_time})."

#### Rule 6: Break Within Shift Hours (Overnight Shifts)
- **Constraint**: For overnight shifts (where `end_time` < `start_time`):
  - If `break_start` >= `start_time`, then `break_end` must be <= 23:59:59 OR >= 00:00:00 and <= `end_time`
  - If `break_start` >= 00:00:00 and `break_start` <= `end_time`, then `break_end` must be <= `end_time`
  - Break cannot span midnight (simplified handling)
- **Rationale**: Overnight shifts require special handling for time comparisons
- **Error Message**: "Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight."

#### Rule 7: Break Format Validation
- **Constraint**: Both `break_start` and `break_end` must be valid time strings in HH:MM:SS or HH:MM format
- **Rationale**: Ensures database compatibility and prevents type errors
- **Error Message**: "Invalid time format. Please use HH:MM or HH:MM:SS format."

### 2. Frontend Validation (Vue.js)

#### Implementation Approach

**File**: `resources/js/pages/Shifts/CreateShift.vue` and `resources/js/pages/Shifts/EditShift.vue`

**Validation Strategy**:
1. **Reactive Validation**: Use Vue `computed` properties to calculate validation state
2. **Real-Time Feedback**: Validate on `@blur` and `@input` events
3. **Error State Management**: Use reactive `errors` object to track validation failures
4. **Submit Prevention**: Disable submit button when validation fails

**Code Structure**:

```typescript
// Composable: useShiftBreakValidation.ts
import { computed, ref, Ref } from 'vue';

interface ShiftFormData {
  start_time: string;
  end_time: string;
  break_start: string | null;
  break_end: string | null;
}

interface BreakValidationErrors {
  break_start?: string;
  break_end?: string;
  break_duration?: string;
}

export function useShiftBreakValidation(formData: Ref<ShiftFormData>) {
  const errors = ref<BreakValidationErrors>({});

  // Helper: Check if shift is overnight
  const isOvernightShift = computed(() => {
    if (!formData.value.start_time || !formData.value.end_time) return false;
    return formData.value.end_time < formData.value.start_time;
  });

  // Helper: Parse time string to minutes since midnight
  const timeToMinutes = (time: string): number => {
    const [hours, minutes] = time.split(':').map(Number);
    return hours * 60 + minutes;
  };

  // Helper: Calculate duration in minutes
  const calculateDuration = (start: string, end: string): number => {
    return timeToMinutes(end) - timeToMinutes(start);
  };

  // Validation: Break completeness
  const validateBreakCompleteness = (): boolean => {
    const hasStart = !!formData.value.break_start;
    const hasEnd = !!formData.value.break_end;

    if (hasStart && !hasEnd) {
      errors.value.break_end = "Both break start and end times are required.";
      return false;
    }
    if (!hasStart && hasEnd) {
      errors.value.break_start = "Both break start and end times are required.";
      return false;
    }
    return true;
  };

  // Validation: Break time order
  const validateBreakOrder = (): boolean => {
    if (!formData.value.break_start || !formData.value.break_end) return true;

    if (formData.value.break_start >= formData.value.break_end) {
      errors.value.break_start = "Break start time must be before break end time.";
      return false;
    }
    return true;
  };

  // Validation: Break duration
  const validateBreakDuration = (): boolean => {
    if (!formData.value.break_start || !formData.value.break_end) return true;

    const duration = calculateDuration(
      formData.value.break_start,
      formData.value.break_end
    );

    if (duration < 1) {
      errors.value.break_duration = "Break duration must be at least 1 minute.";
      return false;
    }

    if (duration > 120) { // 2 hours
      errors.value.break_duration = "Break duration cannot exceed 2 hours.";
      return false;
    }

    return true;
  };

  // Validation: Break within shift hours
  const validateBreakWithinShift = (): boolean => {
    if (!formData.value.break_start || !formData.value.break_end) return true;
    if (!formData.value.start_time || !formData.value.end_time) return true;

    const shiftStart = formData.value.start_time;
    const shiftEnd = formData.value.end_time;
    const breakStart = formData.value.break_start;
    const breakEnd = formData.value.break_end;

    if (isOvernightShift.value) {
      // Overnight shift: break cannot span midnight
      if (breakStart < shiftStart && breakStart > shiftEnd) {
        errors.value.break_start = `Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight.`;
        return false;
      }
      if (breakEnd < shiftStart && breakEnd > shiftEnd) {
        errors.value.break_end = `Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight.`;
        return false;
      }
    } else {
      // Standard shift
      if (breakStart < shiftStart || breakStart > shiftEnd) {
        errors.value.break_start = `Break start time must be between ${shiftStart} and ${shiftEnd}.`;
        return false;
      }
      if (breakEnd < shiftStart || breakEnd > shiftEnd) {
        errors.value.break_end = `Break end time must be between ${shiftStart} and ${shiftEnd}.`;
        return false;
      }
    }

    return true;
  };

  // Run all validations
  const validate = (): boolean => {
    errors.value = {}; // Clear previous errors

    const checks = [
      validateBreakCompleteness(),
      validateBreakOrder(),
      validateBreakDuration(),
      validateBreakWithinShift(),
    ];

    return checks.every(check => check === true);
  };

  // Computed: Is form valid?
  const isValid = computed(() => {
    return Object.keys(errors.value).length === 0;
  });

  return {
    errors,
    isValid,
    validate,
  };
}
```

**Usage in Component**:

```vue
<script setup lang="ts">
import { ref, watch } from 'vue';
import { useShiftBreakValidation } from '@/composables/useShiftBreakValidation';

const form = ref({
  name: '',
  start_time: '09:00',
  end_time: '17:00',
  break_start: null,
  break_end: null,
});

const { errors, isValid, validate } = useShiftBreakValidation(form);

// Validate on field changes
watch(() => [form.value.break_start, form.value.break_end], () => {
  validate();
});

const submit = () => {
  if (!validate()) {
    return; // Prevent submission
  }
  // Submit form...
};
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Break start input -->
    <div>
      <label>Break Start Time</label>
      <input
        v-model="form.break_start"
        type="time"
        @blur="validate"
        :class="{ 'border-red-500': errors.break_start }"
      />
      <p v-if="errors.break_start" class="text-red-500 text-sm">
        {{ errors.break_start }}
      </p>
    </div>

    <!-- Break end input -->
    <div>
      <label>Break End Time</label>
      <input
        v-model="form.break_end"
        type="time"
        @blur="validate"
        :class="{ 'border-red-500': errors.break_end }"
      />
      <p v-if="errors.break_end" class="text-red-500 text-sm">
        {{ errors.break_end }}
      </p>
    </div>

    <!-- Submit button -->
    <button type="submit" :disabled="!isValid">
      Create Shift
    </button>
  </form>
</template>
```

### 3. Backend Validation (Laravel)

#### Implementation Approach

**File**: `app/Http/Requests/StoreShiftRequest.php` and `app/Http/Requests/UpdateShiftRequest.php`

**Validation Strategy**:
1. **Form Request Validation**: Use Laravel's form request classes
2. **Custom Validation Rules**: Create custom rule classes for complex logic
3. **Conditional Validation**: Use `required_with` and `prohibits` for break completeness
4. **Error Message Customization**: Override default messages with user-friendly text

**Code Structure**:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\BreakWithinShiftHours;
use App\Rules\BreakDurationValid;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'break_start' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:break_end',
                'before:break_end',
                new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
            ],
            'break_end' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:break_start',
                'after:break_start',
                new BreakDurationValid($this->input('break_start')),
                new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'break_start.required_with' => 'Both break start and end times are required. Please provide both or leave both empty.',
            'break_end.required_with' => 'Both break start and end times are required. Please provide both or leave both empty.',
            'break_start.before' => 'Break start time must be before break end time.',
            'break_end.after' => 'Break start time must be before break end time.',
            'break_start.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
            'break_end.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
        ];
    }
}
```

**Custom Validation Rule: BreakWithinShiftHours**

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class BreakWithinShiftHours implements ValidationRule
{
    protected string|null $shiftStart;
    protected string|null $shiftEnd;

    public function __construct(?string $shiftStart, ?string $shiftEnd)
    {
        $this->shiftStart = $shiftStart;
        $this->shiftEnd = $shiftEnd;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value || !$this->shiftStart || !$this->shiftEnd) {
            return; // Skip if any required value is missing
        }

        $breakTime = Carbon::createFromFormat('H:i:s', $value);
        $shiftStart = Carbon::createFromFormat('H:i:s', $this->shiftStart);
        $shiftEnd = Carbon::createFromFormat('H:i:s', $this->shiftEnd);

        $isOvernightShift = $shiftEnd->lessThan($shiftStart);

        if ($isOvernightShift) {
            // For overnight shifts, break must be within shift hours
            // and cannot span midnight (simplified)
            $isValid = (
                $breakTime->greaterThanOrEqualTo($shiftStart) ||
                $breakTime->lessThanOrEqualTo($shiftEnd)
            );

            if (!$isValid) {
                $fail("Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight.");
            }
        } else {
            // For standard shifts, break must be between start and end
            if ($breakTime->lessThan($shiftStart) || $breakTime->greaterThan($shiftEnd)) {
                $fail("Break times must fall within shift working hours ({$this->shiftStart} - {$this->shiftEnd}).");
            }
        }
    }
}
```

**Custom Validation Rule: BreakDurationValid**

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class BreakDurationValid implements ValidationRule
{
    protected string|null $breakStart;
    protected int $minDuration = 1; // minutes
    protected int $maxDuration = 120; // 2 hours

    public function __construct(?string $breakStart)
    {
        $this->breakStart = $breakStart;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value || !$this->breakStart) {
            return; // Skip if break times are not provided
        }

        $breakStart = Carbon::createFromFormat('H:i:s', $this->breakStart);
        $breakEnd = Carbon::createFromFormat('H:i:s', $value);

        $duration = $breakEnd->diffInMinutes($breakStart);

        if ($duration < $this->minDuration) {
            $fail("Break duration must be at least {$this->minDuration} minute.");
        }

        if ($duration > $this->maxDuration) {
            $fail("Break duration cannot exceed " . ($this->maxDuration / 60) . " hours. Please verify your break times.");
        }
    }
}
```

### 4. Error Message Specifications

All error messages shall follow these guidelines:

1. **Clarity**: Use plain language, avoid technical jargon
2. **Specificity**: Indicate the exact field and constraint violated
3. **Actionability**: Guide the user on how to fix the issue
4. **Consistency**: Use the same message format across frontend and backend

**Error Message Templates**:

| Validation Failure | Error Message |
|-------------------|--------------|
| Missing break_start when break_end provided | "Both break start and end times are required. Please provide both or leave both empty." |
| Missing break_end when break_start provided | "Both break start and end times are required. Please provide both or leave both empty." |
| break_start >= break_end | "Break start time must be before break end time." |
| Duration < 1 minute | "Break duration must be at least 1 minute." |
| Duration > 2 hours | "Break duration cannot exceed 2 hours. Please verify your break times." |
| Break outside shift (standard) | "Break times must fall within shift working hours ({start_time} - {end_time})." |
| Break outside shift (overnight) | "Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight." |
| Invalid time format | "Invalid time format. Please use HH:MM:SS format." |

### 5. Testing Requirements

#### Unit Tests (Pest PHP)

**File**: `tests/Unit/ShiftBreakValidationTest.php`

```php
<?php

use App\Rules\BreakWithinShiftHours;
use App\Rules\BreakDurationValid;

describe('BreakWithinShiftHours Rule', function () {
    test('passes when break is within standard shift hours', function () {
        $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
        $passes = true;

        $rule->validate('break_start', '10:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeTrue();
    });

    test('fails when break is before shift start', function () {
        $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
        $passes = true;

        $rule->validate('break_start', '08:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeFalse();
    });

    test('fails when break is after shift end', function () {
        $rule = new BreakWithinShiftHours('09:00:00', '17:00:00');
        $passes = true;

        $rule->validate('break_end', '18:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeFalse();
    });

    test('passes when break is within overnight shift hours', function () {
        $rule = new BreakWithinShiftHours('22:00:00', '06:00:00');
        $passes = true;

        $rule->validate('break_start', '23:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeTrue();
    });
});

describe('BreakDurationValid Rule', function () {
    test('passes when break duration is 30 minutes', function () {
        $rule = new BreakDurationValid('12:00:00');
        $passes = true;

        $rule->validate('break_end', '12:30:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeTrue();
    });

    test('fails when break duration is less than 1 minute', function () {
        $rule = new BreakDurationValid('12:00:00');
        $passes = true;

        $rule->validate('break_end', '12:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeFalse();
    });

    test('fails when break duration exceeds 2 hours', function () {
        $rule = new BreakDurationValid('12:00:00');
        $passes = true;

        $rule->validate('break_end', '15:00:00', function ($message) use (&$passes) {
            $passes = false;
        });

        expect($passes)->toBeFalse();
    });
});
```

#### Feature Tests

**File**: `tests/Feature/ShiftBreakValidationTest.php`

```php
<?php

use App\Models\User;
use App\Models\Shift;

describe('Shift Creation with Break Validation', function () {
    test('creates shift with valid break times', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'name' => 'Morning Shift',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    });

    test('rejects shift when break_start is missing but break_end is provided', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_end' => '13:00:00',
        ]);

        $response->assertSessionHasErrors(['break_end']);
    });

    test('rejects shift when break is outside shift hours', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '08:00:00',
            'break_end' => '09:00:00',
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });

    test('rejects shift when break_start is after break_end', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start' => '13:00:00',
            'break_end' => '12:00:00',
        ]);

        $response->assertSessionHasErrors(['break_start']);
    });

    test('accepts shift with no break times', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'name' => 'Morning Shift',
            'break_start' => null,
            'break_end' => null,
        ]);
    });
});
```

## External Dependencies

1. **Laravel Validation System** (built-in)
   - `Illuminate\Foundation\Http\FormRequest`
   - `Illuminate\Contracts\Validation\ValidationRule`

2. **Carbon Date/Time Library** (built-in with Laravel)
   - Used for time comparison and duration calculation
   - Version: ^3.0

3. **Vue 3 Composition API** (already in use)
   - `ref`, `computed`, `watch` for reactive validation
   - Version: ^3.4

4. **TypeScript** (already in use)
   - Type safety for validation composables
   - Version: ^5.0

## Approach

### Implementation Strategy

1. **Phase 1: Backend Validation (Priority: High)**
   - Create custom validation rule classes
   - Implement form request classes
   - Write unit tests for validation rules
   - Write feature tests for shift creation/editing

2. **Phase 2: Frontend Validation (Priority: High)**
   - Create validation composable
   - Update shift creation/edit forms
   - Add real-time error feedback
   - Style error states

3. **Phase 3: Error Message Refinement (Priority: Medium)**
   - Review error messages for clarity
   - Add contextual help text
   - Implement field-level tooltips

4. **Phase 4: Documentation (Priority: Medium)**
   - Update API documentation
   - Add code comments
   - Create troubleshooting guide

### Performance Considerations

- **Frontend**: Debounce validation on input events (300ms) to reduce excessive calculations
- **Backend**: Validation rules are executed synchronously but are lightweight (< 1ms per rule)
- **Caching**: No caching required for validation logic

### Security Considerations

- **Input Sanitization**: All time inputs are validated against strict format (H:i:s)
- **SQL Injection**: Using Eloquent ORM prevents SQL injection
- **XSS Prevention**: Error messages are escaped when rendered in Vue templates

### Backwards Compatibility

- Existing shifts without break times remain valid (both fields nullable)
- Migration already applied, no schema changes required
- No data migration needed
