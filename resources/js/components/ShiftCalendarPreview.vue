<script setup lang="ts">
import { computed } from 'vue'
import { Calendar } from 'lucide-vue-next'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  color_code?: string
}

interface ScheduleDay {
  date: string
  shift: Shift | null
  isToday: boolean
  isWeekend: boolean
}

interface Props {
  schedule: ScheduleDay[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false
})

const weeks = computed(() => {
  const result: ScheduleDay[][] = []
  let week: ScheduleDay[] = []

  props.schedule.forEach((day, index) => {
    week.push(day)
    if (week.length === 7 || index === props.schedule.length - 1) {
      result.push([...week])
      week = []
    }
  })

  return result
})

const formatDate = (dateStr: string) => {
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const getShiftColor = (shift: Shift | null) => {
  if (!shift) return 'bg-muted/30 dark:bg-muted/10'
  return shift.color_code || 'bg-blue-500'
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
      <Calendar class="h-4 w-4" />
      <span>30-Day Schedule Preview</span>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="h-20 bg-muted/30 animate-pulse rounded-lg"></div>
    </div>

    <div v-else class="space-y-2">
      <!-- Week Headers -->
      <div class="grid grid-cols-7 gap-2 text-xs font-medium text-muted-foreground text-center mb-2">
        <div>Sun</div>
        <div>Mon</div>
        <div>Tue</div>
        <div>Wed</div>
        <div>Thu</div>
        <div>Fri</div>
        <div>Sat</div>
      </div>

      <!-- Calendar Weeks -->
      <div v-for="(week, weekIndex) in weeks" :key="weekIndex" class="grid grid-cols-7 gap-2">
        <div
          v-for="(day, dayIndex) in week"
          :key="dayIndex"
          class="relative aspect-square rounded-lg border transition-all hover:shadow-md"
          :class="{
            'border-primary border-2': day.isToday,
            'bg-muted/20': day.isWeekend,
            'bg-card': !day.isWeekend
          }"
        >
          <!-- Date -->
          <div class="absolute top-1 left-1 text-xs font-medium" :class="{
            'text-primary': day.isToday,
            'text-muted-foreground': !day.isToday
          }">
            {{ formatDate(day.date) }}
          </div>

          <!-- Shift Info -->
          <div v-if="day.shift" class="absolute inset-0 flex flex-col items-center justify-center p-2 pt-6">
            <div
              class="w-full h-full rounded flex flex-col items-center justify-center text-white text-[10px] font-medium p-1"
              :style="{ backgroundColor: day.shift.color_code || '#3b82f6' }"
            >
              <div class="truncate w-full text-center">{{ day.shift.name }}</div>
              <div class="text-[9px] opacity-90 mt-0.5">
                {{ day.shift.start_time.slice(0, 5) }} - {{ day.shift.end_time.slice(0, 5) }}
              </div>
            </div>
          </div>

          <!-- No Shift -->
          <div v-else class="absolute inset-0 flex items-center justify-center">
            <div class="text-xs text-muted-foreground">-</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-3 pt-4 border-t text-xs">
      <div class="flex items-center gap-1.5">
        <div class="w-3 h-3 rounded border-2 border-primary"></div>
        <span class="text-muted-foreground">Today</span>
      </div>
      <div class="flex items-center gap-1.5">
        <div class="w-3 h-3 rounded bg-muted/20 border"></div>
        <span class="text-muted-foreground">Weekend</span>
      </div>
    </div>
  </div>
</template>
