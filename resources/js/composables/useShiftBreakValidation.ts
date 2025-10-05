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
      errors.value.break_end = 'Both break start and end times are required.';
      return false;
    }
    if (!hasStart && hasEnd) {
      errors.value.break_start = 'Both break start and end times are required.';
      return false;
    }
    return true;
  };

  // Validation: Break time order
  const validateBreakOrder = (): boolean => {
    if (!formData.value.break_start || !formData.value.break_end) return true;

    if (formData.value.break_start >= formData.value.break_end) {
      errors.value.break_start = 'Break start time must be before break end time.';
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
      errors.value.break_duration = 'Break duration must be at least 1 minute.';
      return false;
    }

    if (duration > 120) {
      // 2 hours
      errors.value.break_duration = 'Break duration cannot exceed 2 hours.';
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

    return checks.every((check) => check === true);
  };

  // Computed: Is form valid?
  const isValid = computed(() => {
    return Object.keys(errors.value).length === 0;
  });

  return {
    errors,
    isValid,
    validate,
    isOvernightShift,
  };
}
