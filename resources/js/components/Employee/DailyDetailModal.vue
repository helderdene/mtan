<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Clock, AlertTriangle, CheckCircle2 } from 'lucide-vue-next'

interface Props {
  date: string
  open: boolean
}

interface AttendanceRecord {
  id: number
  recorded_at: string
  direction: string
  confidence: number
  is_manual_correction: boolean
}

interface Violation {
  id: number
  type: string
  severity: string
  status: string
  description: string
}

interface DailyDetail {
  summary: {
    date: string
    status: string
    total_work_hours: number
    total_work_minutes: number
    total_break_hours: number
    total_break_minutes: number
    overtime_hours: number
    overtime_minutes: number
    first_check_in: string | null
    last_check_out: string | null
    is_complete: boolean
  }
  records: AttendanceRecord[]
  violations: Violation[]
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const loading = ref(false)
const dailyDetail = ref<DailyDetail | null>(null)

const severityColor = (severity: string) => {
  const colors = {
    minor: 'default',
    moderate: 'secondary',
    major: 'destructive',
    critical: 'destructive',
  }
  return colors[severity as keyof typeof colors] || 'default'
}

const directionLabel = (direction: string) => {
  const labels = {
    'check-in': 'Check In',
    'check-out': 'Check Out',
    'break-start': 'Break Start',
    'break-end': 'Break End',
  }
  return labels[direction as keyof typeof labels] || direction
}

const formatTime = (time: string) => {
  return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const loadDailyDetail = async () => {
  loading.value = true
  try {
    const response = await fetch(`/api/v1/employee/attendance/daily/${props.date}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      throw new Error('Failed to load daily detail')
    }

    const result = await response.json()

    if (result.success && result.data) {
      dailyDetail.value = result.data
    }
  } catch (error) {
    console.error('Error loading daily detail:', error)
  } finally {
    loading.value = false
  }
}

watch(() => props.open, (newValue) => {
  if (newValue) {
    loadDailyDetail()
  }
})

onMounted(() => {
  if (props.open) {
    loadDailyDetail()
  }
})
</script>

<template>
  <Dialog :open="open" @update:open="(val) => !val && emit('close')">
    <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>Attendance Detail - {{ date }}</DialogTitle>
        <DialogDescription>
          View your check-in/out times, work hours breakdown, and any violations
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="text-muted-foreground">Loading...</div>
      </div>

      <div v-else-if="dailyDetail" class="space-y-6">
        <!-- Summary Card -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Clock class="h-5 w-5" />
              Work Hours Summary
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <div class="text-sm text-muted-foreground">Status</div>
                <Badge :variant="dailyDetail.summary.status === 'present' ? 'default' : 'secondary'" class="mt-1">
                  {{ dailyDetail.summary.status }}
                </Badge>
              </div>
              <div>
                <div class="text-sm text-muted-foreground">Total Work Hours</div>
                <div class="text-2xl font-bold">{{ dailyDetail.summary.total_work_hours }}h</div>
              </div>
              <div>
                <div class="text-sm text-muted-foreground">First Check-in</div>
                <div class="font-semibold">{{ dailyDetail.summary.first_check_in ? formatTime(dailyDetail.summary.first_check_in) : 'N/A' }}</div>
              </div>
              <div>
                <div class="text-sm text-muted-foreground">Last Check-out</div>
                <div class="font-semibold">{{ dailyDetail.summary.last_check_out ? formatTime(dailyDetail.summary.last_check_out) : 'N/A' }}</div>
              </div>
              <div>
                <div class="text-sm text-muted-foreground">Break Time</div>
                <div class="font-semibold">{{ dailyDetail.summary.total_break_hours }}h {{ dailyDetail.summary.total_break_minutes % 60 }}m</div>
              </div>
              <div>
                <div class="text-sm text-muted-foreground">Overtime</div>
                <div class="font-semibold">{{ dailyDetail.summary.overtime_hours }}h {{ dailyDetail.summary.overtime_minutes % 60 }}m</div>
              </div>
            </div>

            <div v-if="!dailyDetail.summary.is_complete" class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
              <AlertTriangle class="h-4 w-4" />
              <span>Day is incomplete - still checked in</span>
            </div>
          </CardContent>
        </Card>

        <!-- Attendance Records -->
        <Card>
          <CardHeader>
            <CardTitle>Attendance Records</CardTitle>
            <CardDescription>All check-in/out and break events for this day</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div
                v-for="record in dailyDetail.records"
                :key="record.id"
                class="flex items-center justify-between p-3 border rounded-lg"
              >
                <div class="flex items-center gap-4">
                  <div class="text-sm font-medium">
                    {{ new Date(record.recorded_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}
                  </div>
                  <Badge variant="outline">{{ directionLabel(record.direction) }}</Badge>
                  <div v-if="record.is_manual_correction" class="flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400">
                    <CheckCircle2 class="h-4 w-4" />
                    <span>Corrected</span>
                  </div>
                </div>
                <div class="text-sm text-muted-foreground">
                  Confidence: {{ record.confidence }}%
                </div>
              </div>

              <div v-if="dailyDetail.records.length === 0" class="text-center py-6 text-muted-foreground">
                No attendance records for this day
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Violations -->
        <Card v-if="dailyDetail.violations.length > 0">
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-destructive">
              <AlertTriangle class="h-5 w-5" />
              Violations
            </CardTitle>
            <CardDescription>Attendance violations detected for this day</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div
                v-for="violation in dailyDetail.violations"
                :key="violation.id"
                class="flex items-start justify-between p-3 border rounded-lg"
              >
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <Badge :variant="severityColor(violation.severity)">
                      {{ violation.severity }}
                    </Badge>
                    <span class="font-medium">{{ violation.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</span>
                  </div>
                  <p class="text-sm text-muted-foreground">{{ violation.description }}</p>
                </div>
                <Badge variant="outline">{{ violation.status }}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-else class="text-center py-6 text-muted-foreground">
        No data available for this date
      </div>
    </DialogContent>
  </Dialog>
</template>
