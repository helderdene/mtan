<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import RotationSequenceBuilder from '@/components/RotationSequenceBuilder.vue'
import ShiftCalendarPreview from '@/components/ShiftCalendarPreview.vue'
import { Save, X, Repeat, Calendar as CalendarIcon, Eye } from 'lucide-vue-next'
import { dashboard } from '@/routes'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  color_code?: string
}

interface SequenceItem {
  shift_id: number
  duration_days: number
  shift?: Shift
}

interface RotationPattern {
  id: number
  name: string
  description: string | null
  cycle_type: 'daily' | 'weekly' | 'monthly'
  cycle_duration: number
  sequence: SequenceItem[]
  is_active: boolean
}

interface Props {
  shifts: Shift[]
  pattern?: RotationPattern
}

const props = defineProps<Props>()

const isEdit = computed(() => !!props.pattern)

const form = useForm({
  name: props.pattern?.name || '',
  description: props.pattern?.description || '',
  cycle_type: props.pattern?.cycle_type || 'weekly',
  cycle_duration: props.pattern?.cycle_duration || 1,
  sequence: props.pattern?.sequence || [],
  is_active: props.pattern?.is_active ?? true,
})

const showPreview = ref(false)
const previewSchedule = ref<any[]>([])
const previewLoading = ref(false)

const loadPreview = async () => {
  if (form.sequence.length === 0) {
    return
  }

  showPreview.value = true
  previewLoading.value = true

  try {
    // Generate 30-day preview
    const schedule = []
    const today = new Date()
    let sequenceIndex = 0
    let daysInCurrentShift = 0

    for (let i = 0; i < 30; i++) {
      const date = new Date(today)
      date.setDate(today.getDate() + i)

      const currentItem = form.sequence[sequenceIndex]
      const shift = props.shifts.find(s => s.id === currentItem.shift_id)

      schedule.push({
        date: date.toISOString().split('T')[0],
        shift: shift || null,
        isToday: i === 0,
        isWeekend: date.getDay() === 0 || date.getDay() === 6
      })

      daysInCurrentShift++
      if (daysInCurrentShift >= currentItem.duration_days) {
        sequenceIndex = (sequenceIndex + 1) % form.sequence.length
        daysInCurrentShift = 0
      }
    }

    previewSchedule.value = schedule
  } finally {
    previewLoading.value = false
  }
}

const submit = () => {
  if (isEdit.value) {
    form.put(`/rotation-patterns/${props.pattern!.id}`)
  } else {
    form.post('/rotation-patterns')
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit Rotation Pattern' : 'Create Rotation Pattern'" />

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
          href="/rotation-patterns"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Rotation Patterns
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ isEdit ? 'Edit' : 'Create' }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
          {{ isEdit ? 'Edit' : 'Create' }} Rotation Pattern
        </h1>
        <p class="text-muted-foreground mt-2">
          {{ isEdit ? 'Update rotation pattern details' : 'Create a new shift rotation schedule' }}
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="grid lg:grid-cols-2 gap-8">
        <!-- Left Column: Form Fields -->
        <div class="space-y-6">
          <div class="bg-card rounded-xl border p-8 shadow-sm space-y-8">
            <!-- Pattern Name -->
            <div class="relative">
              <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full opacity-10 blur-2xl"></div>
              <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
                <div class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-500">
                  <Repeat class="h-4 w-4 text-white" />
                </div>
                Pattern Information
              </h3>
              <Label for="name" class="text-sm font-medium">Pattern Name *</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                class="mt-2 transition-all focus:ring-2 focus:ring-blue-500/20"
                placeholder="e.g., 3-Week Nursing Rotation"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive mt-1">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- Description -->
            <div>
              <Label for="description" class="text-sm font-medium">Description</Label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="mt-2 flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-all focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="Optional description of this rotation pattern"
                :class="{ 'border-destructive': form.errors.description }"
              />
              <p v-if="form.errors.description" class="text-sm text-destructive mt-1">
                {{ form.errors.description }}
              </p>
            </div>

            <!-- Cycle Type -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label for="cycle_type" class="text-sm font-medium">Cycle Type *</Label>
                <select
                  id="cycle_type"
                  v-model="form.cycle_type"
                  class="mt-2 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  :class="{ 'border-destructive': form.errors.cycle_type }"
                >
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <p v-if="form.errors.cycle_type" class="text-sm text-destructive mt-1">
                  {{ form.errors.cycle_type }}
                </p>
              </div>

              <div>
                <Label for="cycle_duration" class="text-sm font-medium">Cycle Duration *</Label>
                <Input
                  id="cycle_duration"
                  v-model.number="form.cycle_duration"
                  type="number"
                  min="1"
                  class="mt-2"
                  :class="{ 'border-destructive': form.errors.cycle_duration }"
                />
                <p v-if="form.errors.cycle_duration" class="text-sm text-destructive mt-1">
                  {{ form.errors.cycle_duration }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">
                  Number of {{ form.cycle_type === 'daily' ? 'days' : form.cycle_type === 'weekly' ? 'weeks' : 'months' }} per cycle
                </p>
              </div>
            </div>

            <!-- Is Active -->
            <div class="flex items-center space-x-2">
              <Checkbox
                id="is_active"
                v-model:checked="form.is_active"
              />
              <Label for="is_active" class="text-sm font-normal cursor-pointer">
                Active pattern (can be assigned to employees)
              </Label>
            </div>
          </div>

          <!-- Rotation Sequence -->
          <div class="bg-card rounded-xl border p-8 shadow-sm">
            <RotationSequenceBuilder
              v-model="form.sequence"
              :available-shifts="shifts"
            />
            <p v-if="form.errors.sequence" class="text-sm text-destructive mt-2">
              {{ form.errors.sequence }}
            </p>
          </div>
        </div>

        <!-- Right Column: Preview -->
        <div class="space-y-6">
          <div class="bg-card rounded-xl border p-8 shadow-sm space-y-6 sticky top-4">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <div class="p-2 rounded-lg bg-gradient-to-br from-emerald-500 to-green-500">
                  <CalendarIcon class="h-4 w-4 text-white" />
                </div>
                Schedule Preview
              </h3>
              <Button
                type="button"
                size="sm"
                variant="outline"
                @click="loadPreview"
                :disabled="form.sequence.length === 0"
              >
                <Eye class="h-4 w-4 mr-2" />
                Preview
              </Button>
            </div>

            <div v-if="!showPreview" class="text-center py-12 border-2 border-dashed rounded-lg">
              <CalendarIcon class="h-12 w-12 mx-auto text-muted-foreground/50 mb-3" />
              <p class="text-sm text-muted-foreground">
                Add shifts to the sequence and click "Preview" to see the 30-day schedule
              </p>
            </div>

            <ShiftCalendarPreview
              v-else
              :schedule="previewSchedule"
              :loading="previewLoading"
            />
          </div>
        </div>

        <!-- Actions (Full Width) -->
        <div class="lg:col-span-2">
          <div class="rounded-xl border bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:to-gray-950 p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-sm text-muted-foreground">
                <span class="text-destructive">*</span> Required fields
              </p>
              <div class="flex items-center gap-3">
                <Link href="/rotation-patterns">
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
                  {{ isEdit ? 'Update' : 'Create' }} Pattern
                </Button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
