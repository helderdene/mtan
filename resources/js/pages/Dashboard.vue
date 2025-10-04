<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Users, Smartphone, Briefcase, Activity, TrendingUp, Clock, CheckCircle, Bell } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import employees from '@/routes/employees'
import devices from '@/routes/devices'
import departments from '@/routes/departments'
import attendance from '@/routes/attendance'
import { ref, onMounted, onUnmounted } from 'vue'

interface Props {
  stats: {
    total_employees: number
    active_employees: number
    total_devices: number
    active_devices: number
    total_departments: number
    today_attendance: number
    this_week_attendance: number
    avg_daily_attendance: number
  }
  recent_attendance: Array<{
    id: number
    employee: {
      full_name: string
      custom_id: string
    }
    device: {
      name: string
    }
    recorded_at: string
    direction: string
  }>
}

const props = defineProps<Props>()

const showNotification = ref(false)
const notificationMessage = ref('')
let pollInterval: NodeJS.Timeout | null = null

const formatTime = (timestamp: string) => {
  const date = new Date(timestamp)
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const checkAttendanceNotifications = async () => {
  try {
    const response = await fetch('/attendance/notifications')
    const data = await response.json()

    if (data.notifications && data.notifications.length > 0) {
      data.notifications.forEach((notification: any) => {
        notificationMessage.value = notification.message
        showNotification.value = true

        setTimeout(() => {
          showNotification.value = false
        }, 5000)
      })

      // Reload the page data to show updated attendance records
      router.reload({ only: ['stats', 'recent_attendance'] })
    }
  } catch (error) {
    console.error('Failed to check attendance notifications:', error)
  }
}

onMounted(() => {
  pollInterval = setInterval(checkAttendanceNotifications, 2000)
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout>
    <!-- Notification Toast at TOP-RIGHT -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="showNotification"
        class="fixed top-4 right-4 z-50 flex items-center gap-3 px-6 py-4 rounded-lg shadow-lg border bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800"
      >
        <div class="flex-shrink-0">
          <CheckCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
        </div>
        <div class="flex-1">
          <p class="text-sm font-medium text-green-900 dark:text-green-100">
            {{ notificationMessage }}
          </p>
        </div>
        <button
          @click="showNotification = false"
          class="flex-shrink-0 text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </Transition>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Dashboard
          </h1>
          <p class="text-muted-foreground mt-2">
            Welcome back! Here's what's happening with your attendance system today.
          </p>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg shadow-blue-500/20 transition-all hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Users class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-blue-100">Total Employees</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.total_employees }}</p>
              <p class="text-xs text-blue-100 mt-2">
                {{ props.stats.active_employees }} active
              </p>
            </div>
          </div>
        </div>

        <!-- Devices Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Smartphone class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-emerald-100">Devices</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.total_devices }}</p>
              <p class="text-xs text-emerald-100 mt-2">
                {{ props.stats.active_devices }} online
              </p>
            </div>
          </div>
        </div>

        <!-- Departments Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 p-6 shadow-lg shadow-violet-500/20 transition-all hover:shadow-xl hover:shadow-violet-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Briefcase class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-violet-100">Departments</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.total_departments }}</p>
              <p class="text-xs text-violet-100 mt-2">
                Across organization
              </p>
            </div>
          </div>
        </div>

        <!-- Today's Attendance Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 p-6 shadow-lg shadow-orange-500/20 transition-all hover:shadow-xl hover:shadow-orange-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Activity class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-orange-100">Today's Attendance</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.today_attendance }}</p>
              <p class="text-xs text-orange-100 mt-2">
                Check-ins recorded
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance Overview -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- This Week Stats -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">This Week's Summary</h3>
            <div class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-medium">
              7 Days
            </div>
          </div>
          <div class="space-y-5">
            <div class="flex items-center justify-between p-4 rounded-lg bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950/20 dark:to-emerald-950/20 border border-green-100 dark:border-green-900">
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-500/10">
                  <TrendingUp class="h-5 w-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                  <p class="text-sm font-medium text-muted-foreground">Total Check-ins</p>
                  <p class="text-xs text-muted-foreground mt-0.5">This week's total</p>
                </div>
              </div>
              <span class="text-2xl font-bold text-green-600 dark:text-green-400">{{ props.stats.this_week_attendance }}</span>
            </div>
            <div class="flex items-center justify-between p-4 rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border border-blue-100 dark:border-blue-900">
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-blue-500/10">
                  <Clock class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                  <p class="text-sm font-medium text-muted-foreground">Daily Average</p>
                  <p class="text-xs text-muted-foreground mt-0.5">Per day metric</p>
                </div>
              </div>
              <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ props.stats.avg_daily_attendance }}</span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
          <h3 class="text-lg font-semibold mb-6">Quick Actions</h3>
          <div class="grid grid-cols-2 gap-3">
            <Link :href="employees.create().url">
              <Button variant="outline" class="w-full h-auto flex-col gap-2 py-4 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-950/20 transition-all group">
                <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900 group-hover:bg-blue-500 transition-colors">
                  <Users class="h-5 w-5 text-blue-600 dark:text-blue-400 group-hover:text-white" />
                </div>
                <span class="text-sm font-medium">Add Employee</span>
              </Button>
            </Link>
            <Link :href="devices.index().url">
              <Button variant="outline" class="w-full h-auto flex-col gap-2 py-4 hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition-all group">
                <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900 group-hover:bg-emerald-500 transition-colors">
                  <Smartphone class="h-5 w-5 text-emerald-600 dark:text-emerald-400 group-hover:text-white" />
                </div>
                <span class="text-sm font-medium">View Devices</span>
              </Button>
            </Link>
            <Link :href="attendance.live().url">
              <Button variant="outline" class="w-full h-auto flex-col gap-2 py-4 hover:border-orange-500 hover:bg-orange-50 dark:hover:bg-orange-950/20 transition-all group">
                <div class="p-2 rounded-lg bg-orange-100 dark:bg-orange-900 group-hover:bg-orange-500 transition-colors">
                  <Activity class="h-5 w-5 text-orange-600 dark:text-orange-400 group-hover:text-white" />
                </div>
                <span class="text-sm font-medium">Live Feed</span>
              </Button>
            </Link>
            <Link :href="departments.create().url">
              <Button variant="outline" class="w-full h-auto flex-col gap-2 py-4 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-950/20 transition-all group">
                <div class="p-2 rounded-lg bg-violet-100 dark:bg-violet-900 group-hover:bg-violet-500 transition-colors">
                  <Briefcase class="h-5 w-5 text-violet-600 dark:text-violet-400 group-hover:text-white" />
                </div>
                <span class="text-sm font-medium">Add Department</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-6 border-b bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-primary/10">
                <Activity class="h-5 w-5 text-primary" />
              </div>
              <div>
                <h3 class="text-lg font-semibold">Recent Activity</h3>
                <p class="text-xs text-muted-foreground">Latest attendance records</p>
              </div>
            </div>
            <Link :href="attendance.live().url">
              <Button variant="outline" size="sm" class="gap-2">
                View All
                <Activity class="h-3 w-3" />
              </Button>
            </Link>
          </div>
        </div>
        <div class="divide-y">
          <div
            v-for="record in props.recent_attendance"
            :key="record.id"
            class="p-5 flex items-center justify-between hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-900 dark:hover:to-transparent transition-all group"
          >
            <div class="flex items-center gap-4">
              <div class="relative">
                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-white font-semibold shadow-md group-hover:shadow-lg transition-shadow">
                  {{ record.employee.full_name.split(' ').map(n => n[0]).join('') }}
                </div>
                <div
                  :class="[
                    'absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-white dark:border-gray-950',
                    record.direction === 'check-in' ? 'bg-green-500' : 'bg-orange-500'
                  ]"
                />
              </div>
              <div>
                <div class="font-semibold text-sm">{{ record.employee.full_name }}</div>
                <div class="text-xs text-muted-foreground flex items-center gap-2 mt-1">
                  <span
                    :class="[
                      'px-2 py-0.5 rounded-full text-[10px] font-medium',
                      record.direction === 'check-in'
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                    ]"
                  >
                    {{ record.direction === 'check-in' ? '↓ Check In' : '↑ Check Out' }}
                  </span>
                  <span class="text-muted-foreground">•</span>
                  <span>{{ record.device.name }}</span>
                </div>
              </div>
            </div>
            <div class="flex flex-col items-end gap-1">
              <div class="text-sm font-medium">
                {{ formatTime(record.recorded_at) }}
              </div>
              <div class="text-xs text-muted-foreground">
                {{ record.employee.custom_id }}
              </div>
            </div>
          </div>
          <div v-if="props.recent_attendance.length === 0" class="p-16 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 mb-4">
              <Activity class="h-8 w-8 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium text-muted-foreground mb-1">No recent activity</p>
            <p class="text-xs text-muted-foreground">Attendance records will appear here</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
