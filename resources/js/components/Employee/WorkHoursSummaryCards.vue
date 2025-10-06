<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Clock, TrendingUp, Calendar, BarChart3 } from 'lucide-vue-next'

interface Props {
  statistics: {
    total_days_present: number
    total_days_absent: number
    total_work_hours: number
    total_overtime_hours: number
  }
  month: string
}

const props = defineProps<Props>()

const averageDailyHours = computed(() => {
  if (props.statistics.total_days_present === 0) return 0
  return (props.statistics.total_work_hours / props.statistics.total_days_present).toFixed(1)
})

const expectedHours = computed(() => {
  // Assuming 8 hours per day as expected
  return props.statistics.total_days_present * 8
})

const hoursProgress = computed(() => {
  if (expectedHours.value === 0) return 0
  return Math.min(100, (props.statistics.total_work_hours / expectedHours.value) * 100)
})
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    <!-- Total Hours -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle class="text-sm font-medium">Total Hours</CardTitle>
        <Clock class="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ statistics.total_work_hours }}h</div>
        <p class="text-xs text-muted-foreground mt-1">
          {{ month }}
        </p>
        <div class="mt-3 h-2 rounded-full bg-secondary overflow-hidden">
          <div
            class="h-full bg-primary transition-all"
            :style="{ width: `${hoursProgress}%` }"
          />
        </div>
        <p class="text-xs text-muted-foreground mt-1">
          {{ hoursProgress.toFixed(0) }}% of expected ({{ expectedHours }}h)
        </p>
      </CardContent>
    </Card>

    <!-- Overtime Hours -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle class="text-sm font-medium">Overtime Hours</CardTitle>
        <TrendingUp class="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ statistics.total_overtime_hours }}h</div>
        <p class="text-xs text-muted-foreground mt-1">
          Extra time worked
        </p>
      </CardContent>
    </Card>

    <!-- Days Present -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle class="text-sm font-medium">Days Present</CardTitle>
        <Calendar class="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ statistics.total_days_present }}</div>
        <p class="text-xs text-muted-foreground mt-1">
          {{ statistics.total_days_absent }} days absent
        </p>
      </CardContent>
    </Card>

    <!-- Average Daily Hours -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle class="text-sm font-medium">Average Daily Hours</CardTitle>
        <BarChart3 class="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div class="text-2xl font-bold">{{ averageDailyHours }}h</div>
        <p class="text-xs text-muted-foreground mt-1">
          Per working day
        </p>
      </CardContent>
    </Card>
  </div>
</template>
