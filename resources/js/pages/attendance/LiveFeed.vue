<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Clock, User, MapPin, Activity, RefreshCw } from 'lucide-vue-next'

interface AttendanceRecord {
  id: number
  employee: {
    id: number
    custom_id: string
    full_name: string
  }
  device: {
    id: number
    device_id: string
    name: string
    location: string
  }
  recorded_at: string
  direction: string
  recognition_score: string | null
  created_at: string
}

interface Props {
  records: AttendanceRecord[]
  stats: {
    today_total: number
    last_hour: number
    unique_employees: number
  }
}

const props = defineProps<Props>()

const isRefreshing = ref(false)
let refreshInterval: number | null = null

const refresh = () => {
  isRefreshing.value = true
  router.reload({
    only: ['records', 'stats'],
    onFinish: () => {
      isRefreshing.value = false
    },
  })
}

onMounted(() => {
  // Auto-refresh every 5 seconds
  refreshInterval = window.setInterval(() => {
    router.reload({ only: ['records', 'stats'], preserveScroll: true })
  }, 5000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})

const formatTime = (timestamp: string) => {
  const date = new Date(timestamp)
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const formatDate = (timestamp: string) => {
  const date = new Date(timestamp)
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  })
}

const getTimeAgo = (timestamp: string) => {
  const now = new Date()
  const past = new Date(timestamp)
  const diffInSeconds = Math.floor((now.getTime() - past.getTime()) / 1000)

  if (diffInSeconds < 60) return `${diffInSeconds}s ago`
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return `${Math.floor(diffInSeconds / 86400)}d ago`
}
</script>

<template>
  <AppLayout>
    <Head title="Live Attendance Feed" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="route('dashboard')"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Live Feed</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Live Attendance Feed
          </h1>
          <p class="text-muted-foreground mt-2">
            Real-time attendance events from all biometric devices
          </p>
        </div>
        <Button @click="refresh" :disabled="isRefreshing" variant="outline" class="gap-2">
          <RefreshCw :class="['h-4 w-4', isRefreshing && 'animate-spin']" />
          Refresh
        </Button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Today's Check-ins Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg shadow-blue-500/20 transition-all hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-1">
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
              <p class="text-sm font-medium text-blue-100">Today's Check-ins</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.today_total }}</p>
              <p class="text-xs text-blue-100 mt-2">
                Total records
              </p>
            </div>
          </div>
        </div>

        <!-- Last Hour Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Clock class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-emerald-100">Last Hour</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.last_hour }}</p>
              <p class="text-xs text-emerald-100 mt-2">
                Recent activity
              </p>
            </div>
          </div>
        </div>

        <!-- Unique Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 p-6 shadow-lg shadow-violet-500/20 transition-all hover:shadow-xl hover:shadow-violet-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <User class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-violet-100">Unique Employees</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.stats.unique_employees }}</p>
              <p class="text-xs text-violet-100 mt-2">
                Today's count
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Feed -->
      <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-6 border-b bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:dark:to-gray-950">
          <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded-full bg-green-500 animate-pulse shadow-lg shadow-green-500/50"></div>
            <h3 class="text-lg font-semibold">Live Attendance Events</h3>
            <span class="text-sm text-muted-foreground">(Auto-refreshing every 5s)</span>
          </div>
        </div>

        <div class="divide-y">
          <div
            v-for="record in props.records"
            :key="record.id"
            class="p-6 hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-900 dark:hover:to-transparent transition-all group"
          >
            <div class="flex items-start gap-4">
              <!-- Avatar -->
              <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-white font-semibold shrink-0 shadow-md group-hover:shadow-lg transition-shadow">
                {{ record.employee.full_name.split(' ').map(n => n[0]).join('') }}
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-semibold">{{ record.employee.full_name }}</h4>
                  <Badge variant="outline" class="text-xs">
                    {{ record.employee.custom_id }}
                  </Badge>
                  <Badge :variant="record.direction === 'check-in' ? 'default' : 'secondary'" class="text-xs">
                    {{ record.direction === 'check-in' ? 'Check In' : 'Check Out' }}
                  </Badge>
                </div>

                <div class="flex items-center gap-4 text-sm text-muted-foreground">
                  <div class="flex items-center gap-1">
                    <MapPin class="h-4 w-4" />
                    <span>{{ record.device.name }} ({{ record.device.location }})</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <Clock class="h-4 w-4" />
                    <span>{{ formatTime(record.recorded_at) }}</span>
                  </div>
                  <div v-if="record.recognition_score" class="flex items-center gap-1">
                    <Activity class="h-4 w-4" />
                    <span>{{ (parseFloat(record.recognition_score) * 100).toFixed(1) }}% confidence</span>
                  </div>
                </div>
              </div>

              <!-- Timestamp -->
              <div class="text-right text-sm text-muted-foreground shrink-0">
                <div>{{ formatDate(record.recorded_at) }}</div>
                <div class="text-xs">{{ getTimeAgo(record.created_at) }}</div>
              </div>
            </div>
          </div>

          <div v-if="props.records.length === 0" class="p-12 text-center">
            <Activity class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
            <p class="text-muted-foreground">No attendance records yet</p>
            <p class="text-sm text-muted-foreground mt-1">
              Attendance events will appear here in real-time
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
