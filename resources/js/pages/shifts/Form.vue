<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Save, X, Clock, Calendar, AlertCircle, Repeat, Moon, Zap } from 'lucide-vue-next'
import shiftsRoute from '@/routes/shifts'
import { dashboard } from '@/routes'
import { useShiftBreakValidation } from '@/composables/useShiftBreakValidation'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  break_start: string | null
  break_end: string | null
  working_days: string[]
  is_default: boolean
  shift_type: 'fixed' | 'flexible' | 'rotating'
  is_overnight: boolean
  flexible_checkin_start: string | null
  flexible_checkin_end: string | null
  core_hours_required: number | null
}

interface Props {
  shift?: Shift
}

const props = defineProps<Props>()

const isEdit = computed(() => !!props.shift)

const weekDays = [
  { value: 'monday', label: 'Monday' },
  { value: 'tuesday', label: 'Tuesday' },
  { value: 'wednesday', label: 'Wednesday' },
  { value: 'thursday', label: 'Thursday' },
  { value: 'friday', label: 'Friday' },
  { value: 'saturday', label: 'Saturday' },
  { value: 'sunday', label: 'Sunday' },
]

const form = useForm({
  name: props.shift?.name || '',
  shift_type: props.shift?.shift_type || 'fixed',
  start_time: props.shift?.start_time || '09:00:00',
  end_time: props.shift?.end_time || '17:00:00',
  break_start: props.shift?.break_start || '',
  break_end: props.shift?.break_end || '',
  working_days: props.shift?.working_days || ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
  is_default: props.shift?.is_default ?? false,
  is_overnight: props.shift?.is_overnight ?? false,
  flexible_checkin_start: props.shift?.flexible_checkin_start || '',
  flexible_checkin_end: props.shift?.flexible_checkin_end || '',
  core_hours_required: props.shift?.core_hours_required || 8,
})

const toggleWorkingDay = (day: string) => {
  const index = form.working_days.indexOf(day)
  if (index === -1) {
    form.working_days.push(day)
  } else {
    form.working_days.splice(index, 1)
  }
}

// Break validation composable
const { errors: breakErrors, validate: validateBreaks, isOvernightShift } = useShiftBreakValidation(
  computed(() => ({
    start_time: form.start_time,
    end_time: form.end_time,
    break_start: form.break_start || null,
    break_end: form.break_end || null,
  }))
)

// Watch for changes to break times and validate
watch([() => form.break_start, () => form.break_end, () => form.start_time, () => form.end_time], () => {
  validateBreaks()
})

// Helper function to calculate end time for flexible shifts
const calculateEndTime = (startTime: string, hours: number): string => {
  if (!startTime || !hours) return ''

  const [startHours, startMinutes] = startTime.split(':').map(Number)
  const totalMinutes = startHours * 60 + startMinutes + (hours * 60)
  const endHours = Math.floor(totalMinutes / 60) % 24
  const endMinutes = totalMinutes % 60

  return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`
}

const submit = () => {
  // Run frontend validation before submitting
  if (!validateBreaks()) {
    return
  }

  if (isEdit.value) {
    form.put(shiftsRoute.update(props.shift!.id).url)
  } else {
    form.post(shiftsRoute.store().url)
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit Shift' : 'Create Shift'" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <Link
          :href="shiftsRoute.index().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Shifts
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ isEdit ? 'Edit' : 'Create' }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
          {{ isEdit ? 'Edit' : 'Create' }} Shift
        </h1>
        <p class="text-muted-foreground mt-2">
          {{ isEdit ? 'Update shift details' : 'Create a new work shift' }}
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="max-w-3xl space-y-6">
        <div class="bg-card rounded-xl border p-8 shadow-sm space-y-8">
          <!-- Shift Name -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-500">
                <Clock class="h-4 w-4 text-white" />
              </div>
              Shift Information
            </h3>
            <Label for="name" class="text-sm font-medium">Shift Name *</Label>
            <Input
              id="name"
              v-model="form.name"
              type="text"
              class="mt-2 transition-all focus:ring-2 focus:ring-blue-500/20"
              placeholder="e.g., Morning Shift"
              :class="{ 'border-destructive': form.errors.name }"
            />
            <p v-if="form.errors.name" class="text-sm text-destructive mt-1">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Shift Type -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500">
                <Repeat class="h-4 w-4 text-white" />
              </div>
              Shift Type
            </h3>
            <Label for="shift_type" class="text-sm font-medium">Type *</Label>
            <select
              id="shift_type"
              v-model="form.shift_type"
              class="mt-2 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-all file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
              :class="{ 'border-destructive': form.errors.shift_type }"
            >
              <option value="fixed">Fixed Schedule - Standard work hours</option>
              <option value="flexible">Flexible Schedule - Variable check-in window with core hours</option>
              <option value="rotating">Rotating Schedule - Shifts rotate on a schedule</option>
            </select>
            <p v-if="form.errors.shift_type" class="text-sm text-destructive mt-1">
              {{ form.errors.shift_type }}
            </p>
            <p class="text-xs text-muted-foreground mt-2">
              Select the type of shift schedule for this shift
            </p>
          </div>

          <!-- Overnight Shift Toggle -->
          <div class="flex items-center space-x-2 p-4 rounded-lg bg-muted/50 border">
            <Checkbox
              id="is_overnight"
              v-model:checked="form.is_overnight"
            />
            <Label for="is_overnight" class="text-sm font-normal cursor-pointer flex items-center gap-2">
              <Moon class="h-4 w-4" />
              <div>
                <div>Overnight Shift</div>
                <div class="text-xs text-muted-foreground">Shift crosses midnight (e.g., 10 PM - 6 AM)</div>
              </div>
            </Label>
          </div>

          <!-- Shift Time Range -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-emerald-500 to-green-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-emerald-500 to-green-500">
                <Clock class="h-4 w-4 text-white" />
              </div>
              Working Hours
            </h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="start_time" class="text-sm font-medium">Shift Start Time *</Label>
              <Input
                id="start_time"
                v-model="form.start_time"
                type="time"
                step="1"
                class="mt-2 transition-all focus:ring-2 focus:ring-emerald-500/20"
                :class="{ 'border-destructive': form.errors.start_time }"
              />
              <p v-if="form.errors.start_time" class="text-sm text-destructive mt-1">
                {{ form.errors.start_time }}
              </p>
            </div>

              <div>
                <Label for="end_time" class="text-sm font-medium">Shift End Time *</Label>
                <Input
                  id="end_time"
                  v-model="form.end_time"
                  type="time"
                  step="1"
                  class="mt-2 transition-all focus:ring-2 focus:ring-emerald-500/20"
                  :class="{ 'border-destructive': form.errors.end_time }"
                />
                <p v-if="form.errors.end_time" class="text-sm text-destructive mt-1">
                  {{ form.errors.end_time }}
                </p>
              </div>
            </div>
          </div>

          <!-- Flexible Shift Settings -->
          <div v-if="form.shift_type === 'flexible'" class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-cyan-500 to-blue-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-500">
                <Zap class="h-4 w-4 text-white" />
              </div>
              Flexible Shift Configuration
            </h3>

            <!-- Info Box -->
            <div class="mb-6 p-4 rounded-lg bg-cyan-50 dark:bg-cyan-950/30 border border-cyan-200 dark:border-cyan-800">
              <p class="text-sm text-cyan-900 dark:text-cyan-100 flex items-center gap-2">
                <AlertCircle class="w-4 h-4" />
                <span class="font-medium">Flexible shifts allow employees to check in within a time window and work a set number of core hours.</span>
              </p>
              <p class="text-xs text-cyan-700 dark:text-cyan-300 mt-2">
                Example: Check-in window 8:00-10:00 AM, Core hours: 8 hours. Employee checks in at 9:00 AM, must work until 5:00 PM.
              </p>
            </div>

            <!-- Check-in Window -->
            <div class="space-y-4">
              <Label class="text-sm font-medium">Check-in Window *</Label>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <Label for="flexible_checkin_start" class="text-sm font-medium">Earliest Check-in</Label>
                  <Input
                    id="flexible_checkin_start"
                    v-model="form.flexible_checkin_start"
                    type="time"
                    step="1"
                    class="mt-2 transition-all focus:ring-2 focus:ring-cyan-500/20"
                    :class="{ 'border-destructive': form.errors.flexible_checkin_start }"
                  />
                  <p v-if="form.errors.flexible_checkin_start" class="text-sm text-destructive mt-1">
                    {{ form.errors.flexible_checkin_start }}
                  </p>
                  <p class="text-xs text-muted-foreground mt-1">
                    Earliest time employees can check in
                  </p>
                </div>

                <div>
                  <Label for="flexible_checkin_end" class="text-sm font-medium">Latest Check-in</Label>
                  <Input
                    id="flexible_checkin_end"
                    v-model="form.flexible_checkin_end"
                    type="time"
                    step="1"
                    class="mt-2 transition-all focus:ring-2 focus:ring-cyan-500/20"
                    :class="{ 'border-destructive': form.errors.flexible_checkin_end }"
                  />
                  <p v-if="form.errors.flexible_checkin_end" class="text-sm text-destructive mt-1">
                    {{ form.errors.flexible_checkin_end }}
                  </p>
                  <p class="text-xs text-muted-foreground mt-1">
                    Latest time employees can check in
                  </p>
                </div>
              </div>
            </div>

            <!-- Core Hours Required -->
            <div class="mt-6">
              <Label for="core_hours_required" class="text-sm font-medium">Core Hours Required *</Label>
              <Input
                id="core_hours_required"
                v-model.number="form.core_hours_required"
                type="number"
                min="1"
                max="24"
                step="0.5"
                class="mt-2 transition-all focus:ring-2 focus:ring-cyan-500/20"
                :class="{ 'border-destructive': form.errors.core_hours_required }"
              />
              <p v-if="form.errors.core_hours_required" class="text-sm text-destructive mt-1">
                {{ form.errors.core_hours_required }}
              </p>
              <p class="text-xs text-muted-foreground mt-1">
                Number of hours employees must work after check-in (e.g., 8 hours)
              </p>
            </div>

            <!-- Example Calculation -->
            <div v-if="form.flexible_checkin_start && form.flexible_checkin_end && form.core_hours_required" class="mt-6 p-4 rounded-lg bg-muted/50 border">
              <p class="text-xs font-medium mb-2">Example Schedule:</p>
              <div class="text-xs text-muted-foreground space-y-1">
                <p>• Check-in window: {{ form.flexible_checkin_start }} - {{ form.flexible_checkin_end }}</p>
                <p>• Core hours: {{ form.core_hours_required }} hours</p>
                <p class="text-cyan-600 dark:text-cyan-400 font-medium mt-2">
                  If employee checks in at {{ form.flexible_checkin_start }}, they work until
                  {{ calculateEndTime(form.flexible_checkin_start, form.core_hours_required) }}
                </p>
                <p class="text-cyan-600 dark:text-cyan-400 font-medium">
                  If employee checks in at {{ form.flexible_checkin_end }}, they work until
                  {{ calculateEndTime(form.flexible_checkin_end, form.core_hours_required) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Break Time Range -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-orange-500 to-amber-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-orange-500 to-amber-500">
                <Clock class="h-4 w-4 text-white" />
              </div>
              Break Time <span class="text-sm font-normal text-muted-foreground">(Optional)</span>
            </h3>

            <!-- Shift Type Indicator -->
            <div v-if="form.start_time && form.end_time" class="mb-4 p-3 rounded-lg bg-muted/50 border">
              <p class="text-xs text-muted-foreground flex items-center gap-2">
                <AlertCircle class="w-3 h-3" />
                <span v-if="isOvernightShift">
                  Overnight shift detected ({{ form.start_time }} - {{ form.end_time }}).
                  Break times must fall within shift hours and cannot span midnight.
                </span>
                <span v-else>
                  Standard shift ({{ form.start_time }} - {{ form.end_time }}).
                  Break times must be between shift start and end times.
                </span>
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="break_start" class="text-sm font-medium">Break Start Time</Label>
              <Input
                id="break_start"
                v-model="form.break_start"
                type="time"
                step="1"
                class="mt-2 transition-all focus:ring-2 focus:ring-orange-500/20"
                :class="{ 'border-destructive': form.errors.break_start || breakErrors.break_start }"
                @blur="validateBreaks"
              />
              <p v-if="breakErrors.break_start" class="text-sm text-destructive mt-1 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                {{ breakErrors.break_start }}
              </p>
              <p v-else-if="form.errors.break_start" class="text-sm text-destructive mt-1 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                {{ form.errors.break_start }}
              </p>
              <p v-else class="text-xs text-muted-foreground mt-1">
                Leave empty if no break. Must be within shift hours.
              </p>
            </div>

              <div>
                <Label for="break_end" class="text-sm font-medium">Break End Time</Label>
                <Input
                  id="break_end"
                  v-model="form.break_end"
                  type="time"
                  step="1"
                  class="mt-2 transition-all focus:ring-2 focus:ring-orange-500/20"
                  :class="{ 'border-destructive': form.errors.break_end || breakErrors.break_end }"
                  @blur="validateBreaks"
                />
                <p v-if="breakErrors.break_end" class="text-sm text-destructive mt-1 flex items-center gap-1">
                  <AlertCircle class="w-3 h-3" />
                  {{ breakErrors.break_end }}
                </p>
                <p v-else-if="breakErrors.break_duration" class="text-sm text-destructive mt-1 flex items-center gap-1">
                  <AlertCircle class="w-3 h-3" />
                  {{ breakErrors.break_duration }}
                </p>
                <p v-else-if="form.errors.break_end" class="text-sm text-destructive mt-1 flex items-center gap-1">
                  <AlertCircle class="w-3 h-3" />
                  {{ form.errors.break_end }}
                </p>
                <p v-else class="text-xs text-muted-foreground mt-1">
                  Duration: 1 minute to 2 hours. Must be after break start.
                </p>
              </div>
            </div>
          </div>

          <!-- Working Days -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-violet-500 to-purple-500">
                <Calendar class="h-4 w-4 text-white" />
              </div>
              Working Days
            </h3>
            <Label class="text-sm font-medium">Select Working Days *</Label>
            <div class="mt-2 space-y-2">
              <div
                v-for="day in weekDays"
                :key="day.value"
                class="flex items-center space-x-2"
              >
                <Checkbox
                  :id="day.value"
                  :checked="form.working_days.includes(day.value)"
                  @update:checked="toggleWorkingDay(day.value)"
                />
                <Label :for="day.value" class="text-sm font-normal cursor-pointer">
                  {{ day.label }}
                </Label>
              </div>
            </div>
            <p v-if="form.errors.working_days" class="text-sm text-destructive mt-1">
              {{ form.errors.working_days }}
            </p>
          </div>

          <!-- Is Default -->
          <div class="flex items-center space-x-2">
            <Checkbox
              id="is_default"
              v-model:checked="form.is_default"
            />
            <Label for="is_default" class="text-sm font-normal cursor-pointer">
              Set as default shift
            </Label>
          </div>
        </div>

        <!-- Actions -->
        <div class="rounded-xl border bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:to-gray-950 p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
              <span class="text-destructive">*</span> Required fields
            </p>
            <div class="flex items-center gap-3">
              <Link :href="shiftsRoute.index().url">
                <Button type="button" variant="outline" class="gap-2">
                  <X class="w-4 h-4" />
                  Cancel
                </Button>
              </Link>
              <Button
                type="submit"
                :disabled="form.processing"
                class="gap-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 shadow-lg shadow-blue-500/30"
              >
                <Save class="w-4 h-4" />
                {{ isEdit ? 'Update' : 'Create' }} Shift
              </Button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
