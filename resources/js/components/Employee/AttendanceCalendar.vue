<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import DailyDetailModal from './DailyDetailModal.vue'
import WorkHoursSummaryCards from './WorkHoursSummaryCards.vue'

interface DailyAttendanceSummary {
  id: number
  date: string
  status: 'present' | 'absent' | 'half-day' | 'on-leave' | 'holiday'
  total_work_hours: number
  total_work_minutes: number
  overtime_hours: number
  first_check_in: string | null
  last_check_out: string | null
  is_complete: boolean
}

interface CalendarData {
  month: number
  year: number
  summaries: DailyAttendanceSummary[]
  statistics: {
    total_days_present: number
    total_days_absent: number
    total_work_hours: number
    total_overtime_hours: number
  }
}

const currentDate = ref(new Date())
const calendarData = ref<CalendarData | null>(null)
const loading = ref(false)
const selectedDate = ref<string | null>(null)
const showDailyDetail = ref(false)

// Client-side cache for loaded months
const monthCache = ref<Map<string, CalendarData>>(new Map())

const currentMonth = computed(() => currentDate.value.getMonth() + 1)
const currentYear = computed(() => currentDate.value.getFullYear())
const monthYearKey = computed(() => `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}`)

const monthName = computed(() => {
  return currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

const firstDayOfMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
})

const calendarDays = computed(() => {
  const days: Array<{ day: number; date: string; summary: DailyAttendanceSummary | null }> = []

  for (let i = 1; i <= daysInMonth.value; i++) {
    const dateStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(i).padStart(2, '0')}`
    const summary = calendarData.value?.summaries.find(s => s.date === dateStr) || null
    days.push({ day: i, date: dateStr, summary })
  }

  return days
})

const statusColor = (status: string | null) => {
  if (!status) return 'bg-gray-100 dark:bg-gray-800'

  const colors = {
    present: 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 border-green-300 dark:border-green-700',
    absent: 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 border-red-300 dark:border-red-700',
    'half-day': 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700',
    'on-leave': 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700',
    holiday: 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border-gray-300 dark:border-gray-700',
  }

  return colors[status as keyof typeof colors] || 'bg-gray-100 dark:bg-gray-800'
}

const loadCalendarData = async () => {
  // Check cache first
  const cached = monthCache.value.get(monthYearKey.value)
  if (cached) {
    calendarData.value = cached
    return
  }

  loading.value = true
  try {
    const response = await fetch(`/api/v1/employee/attendance/calendar?month=${monthYearKey.value}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      throw new Error('Failed to load calendar data')
    }

    const result = await response.json()

    if (result.success && result.data) {
      calendarData.value = result.data
      // Cache the data
      monthCache.value.set(monthYearKey.value, result.data)
    }
  } catch (error) {
    console.error('Error loading calendar data:', error)
  } finally {
    loading.value = false
  }
}

const previousMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 2, 1)
  loadCalendarData()
}

const nextMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value, 1)
  loadCalendarData()
}

const openDailyDetail = (date: string) => {
  selectedDate.value = date
  showDailyDetail.value = true
}

const closeDailyDetail = () => {
  showDailyDetail.value = false
  selectedDate.value = null
}

onMounted(() => {
  loadCalendarData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Work Hours Summary Cards -->
    <WorkHoursSummaryCards
      v-if="calendarData"
      :statistics="calendarData.statistics"
      :month="monthName"
    />

    <!-- Calendar Card -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>Attendance Calendar</CardTitle>
            <CardDescription>Click on any date to view detailed attendance information</CardDescription>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" size="icon" @click="previousMonth" :disabled="loading">
              <ChevronLeft class="h-4 w-4" />
            </Button>
            <div class="min-w-[200px] text-center font-semibold">
              {{ monthName }}
            </div>
            <Button variant="outline" size="icon" @click="nextMonth" :disabled="loading">
              <ChevronRight class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="text-muted-foreground">Loading calendar...</div>
        </div>

        <div v-else class="grid grid-cols-7 gap-2">
          <!-- Weekday headers -->
          <div
            v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
            :key="day"
            class="text-center font-semibold text-sm text-muted-foreground py-2"
          >
            {{ day }}
          </div>

          <!-- Empty cells for days before month starts -->
          <div
            v-for="i in firstDayOfMonth"
            :key="`empty-${i}`"
            class="aspect-square"
          />

          <!-- Calendar days -->
          <button
            v-for="{ day, date, summary } in calendarDays"
            :key="day"
            @click="summary ? openDailyDetail(date) : null"
            :class="[
              'aspect-square rounded-lg border-2 p-2 text-sm transition-all',
              summary ? statusColor(summary.status) + ' hover:ring-2 hover:ring-offset-2 hover:ring-primary cursor-pointer' : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-800 cursor-default',
            ]"
          >
            <div class="font-semibold">{{ day }}</div>
            <div v-if="summary && summary.total_work_hours > 0" class="text-xs mt-1">
              {{ summary.total_work_hours }}h
            </div>
          </button>
        </div>

        <!-- Legend -->
        <div class="mt-6 pt-6 border-t flex flex-wrap gap-4 text-sm">
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-green-500" />
            <span>Present</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-red-500" />
            <span>Absent</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-yellow-500" />
            <span>Half-day</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-blue-500" />
            <span>On Leave</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded bg-gray-500" />
            <span>Holiday</span>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Daily Detail Modal -->
    <DailyDetailModal
      v-if="selectedDate"
      :date="selectedDate"
      :open="showDailyDetail"
      @close="closeDailyDetail"
    />
  </div>
</template>
