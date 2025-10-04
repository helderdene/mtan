<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Plus, Pencil, Trash2, Clock, Users } from 'lucide-vue-next'
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
  employees_count: number
}

interface PaginatedShifts {
  data: Shift[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface Props {
  shifts: PaginatedShifts
}

const props = defineProps<Props>()

const formatTime = (time: string) => {
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${minutes} ${ampm}`
}

const formatWorkingDays = (days: string[]) => {
  const shortDays = days.map(day => day.substring(0, 3).toUpperCase())
  return shortDays.join(', ')
}

const deleteShift = (id: number) => {
  if (confirm('Are you sure you want to delete this shift?')) {
    router.delete(shiftsRoute.destroy(id).url)
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Shifts" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Shifts</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Shifts
          </h1>
          <p class="text-muted-foreground mt-2">Manage work shifts and schedules</p>
        </div>
        <Link :href="shiftsRoute.create().url">
          <Button class="gap-2">
            <Plus class="w-4 h-4" />
            Create Shift
          </Button>
        </Link>
      </div>

      <!-- Shifts Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="shift in shifts.data"
          :key="shift.id"
          class="group relative overflow-hidden rounded-xl bg-card border shadow-sm p-6 transition-all hover:shadow-xl hover:-translate-y-1"
        >
          <!-- Gradient overlay on hover -->
          <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />

          <!-- Content -->
          <div class="relative">
            <div class="flex items-start justify-between mb-6">
              <div>
                <div class="flex items-center gap-2">
                  <div class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-violet-500 group-hover:from-blue-600 group-hover:to-violet-600 transition-all">
                    <Clock class="w-5 h-5 text-white" />
                  </div>
                  <h3 class="text-lg font-semibold">{{ shift.name }}</h3>
                </div>
                <span
                  v-if="shift.is_default"
                  class="inline-block px-2.5 py-1 text-xs bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full mt-2 shadow-sm"
                >
                  Default Shift
                </span>
              </div>
              <div class="flex items-center gap-1">
                <Link :href="shiftsRoute.edit(shift.id).url">
                  <Button variant="ghost" size="icon" class="hover:bg-primary/10">
                    <Pencil class="w-4 h-4" />
                  </Button>
                </Link>
                <Button variant="ghost" size="icon" @click="deleteShift(shift.id)" class="hover:bg-destructive/10">
                  <Trash2 class="w-4 h-4 text-destructive" />
                </Button>
              </div>
            </div>

            <div class="space-y-4">
              <!-- Time Info -->
              <div class="p-3 rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border border-blue-100 dark:border-blue-900">
                <div class="flex items-center gap-2 mb-1">
                  <Clock class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                  <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Shift Hours</span>
                </div>
                <div class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                  {{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}
                </div>
                <div v-if="shift.break_start && shift.break_end" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                  Break: {{ formatTime(shift.break_start) }} - {{ formatTime(shift.break_end) }}
                </div>
              </div>

              <!-- Employees Count -->
              <div class="p-3 rounded-lg bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-950/20 dark:to-green-950/20 border border-emerald-100 dark:border-emerald-900">
                <div class="flex items-center gap-2">
                  <Users class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                  <span class="text-sm font-medium">
                    <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ shift.employees_count }}</span>
                    <span class="text-emerald-600 dark:text-emerald-400 ml-1">employee{{ shift.employees_count !== 1 ? 's' : '' }}</span>
                  </span>
                </div>
              </div>

              <!-- Working Days -->
              <div class="p-3 rounded-lg bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-950/20 dark:to-purple-950/20 border border-violet-100 dark:border-violet-900">
                <div class="text-xs font-medium text-violet-700 dark:text-violet-300 mb-1">Working Days</div>
                <div class="text-sm font-mono font-semibold text-violet-900 dark:text-violet-100">
                  {{ formatWorkingDays(shift.working_days) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="shifts.data.length === 0" class="text-center py-12">
        <Clock class="w-16 h-16 mx-auto text-muted-foreground/40 mb-4" />
        <h3 class="text-lg font-semibold mb-2">No shifts found</h3>
        <p class="text-muted-foreground mb-6">Get started by creating your first shift</p>
        <Link :href="shiftsRoute.create().url">
          <Button>
            <Plus class="w-4 h-4 mr-2" />
            Create Shift
          </Button>
        </Link>
      </div>

      <!-- Pagination -->
      <div
        v-if="shifts.data.length > 0 && shifts.last_page > 1"
        class="flex items-center justify-center space-x-2"
      >
        <Button
          v-for="page in shifts.last_page"
          :key="page"
          :variant="page === shifts.current_page ? 'default' : 'outline'"
          size="sm"
          @click="router.visit(shiftsRoute.index({ query: { page } }).url)"
        >
          {{ page }}
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
