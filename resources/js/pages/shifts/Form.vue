<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Save, X, Clock, Calendar } from 'lucide-vue-next'
import shiftsRoute from '@/routes/shifts'
import { dashboard } from '@/routes'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  break_start: string | null
  break_end: string | null
  working_days: string[]
  is_default: boolean
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
  start_time: props.shift?.start_time || '09:00:00',
  end_time: props.shift?.end_time || '17:00:00',
  break_start: props.shift?.break_start || '',
  break_end: props.shift?.break_end || '',
  working_days: props.shift?.working_days || ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
  is_default: props.shift?.is_default ?? false,
})

const toggleWorkingDay = (day: string) => {
  const index = form.working_days.indexOf(day)
  if (index === -1) {
    form.working_days.push(day)
  } else {
    form.working_days.splice(index, 1)
  }
}

const submit = () => {
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

          <!-- Break Time Range -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-orange-500 to-amber-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-orange-500 to-amber-500">
                <Clock class="h-4 w-4 text-white" />
              </div>
              Break Time <span class="text-sm font-normal text-muted-foreground">(Optional)</span>
            </h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="break_start" class="text-sm font-medium">Break Start Time</Label>
              <Input
                id="break_start"
                v-model="form.break_start"
                type="time"
                step="1"
                class="mt-2 transition-all focus:ring-2 focus:ring-orange-500/20"
                :class="{ 'border-destructive': form.errors.break_start }"
              />
              <p v-if="form.errors.break_start" class="text-sm text-destructive mt-1">
                {{ form.errors.break_start }}
              </p>
              <p class="text-xs text-muted-foreground mt-1">
                Optional: Define when break period starts
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
                  :class="{ 'border-destructive': form.errors.break_end }"
                />
                <p v-if="form.errors.break_end" class="text-sm text-destructive mt-1">
                  {{ form.errors.break_end }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">
                  Must be after break start time
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
