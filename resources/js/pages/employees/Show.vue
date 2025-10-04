<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Edit,
  Mail,
  Phone,
  Building2,
  Calendar,
  Clock,
  UserCircle,
  Trash2,
  CheckCircle,
  XCircle,
  AlertCircle,
  Plus,
  RefreshCw,
} from 'lucide-vue-next'
import employees from '@/routes/employees'
import { dashboard } from '@/routes'

interface Department {
  id: number
  name: string
}

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  pivot: {
    effective_from: string
    effective_to: string | null
  }
}

interface Device {
  id: number
  device_name: string
  device_id: string
}

interface DeviceEnrollment {
  id: number
  device_id: number
  enrollment_status: 'pending' | 'synced' | 'failed'
  last_synced_at: string | null
  device: Device
}

interface AttendanceRecord {
  id: number
  check_in: string
  check_out: string | null
  date: string
  total_hours: number | null
}

interface Employee {
  id: number
  custom_id: string
  first_name: string
  last_name: string
  full_name: string
  email: string
  phone: string | null
  avatar: string | null
  avatar_url: string | null
  department: Department | null
  is_active: boolean
  hired_at: string | null
  created_at: string
  shifts: Shift[]
  device_enrollments: DeviceEnrollment[]
  attendance_records: AttendanceRecord[]
}

interface Props {
  employee: Employee
  available_shifts: Shift[]
  available_devices: Device[]
}

const props = defineProps<Props>()

const showAddShiftDialog = ref(false)
const showSyncToDeviceDialog = ref(false)

const shiftForm = useForm({
  shift_id: null as number | null,
  effective_from: '',
  effective_to: '',
})

const syncForm = useForm({
  device_id: null as number | null,
})

const addShift = () => {
  shiftForm.post(`/employees/${props.employee.id}/shifts`, {
    preserveScroll: true,
    onSuccess: () => {
      showAddShiftDialog.value = false
      shiftForm.reset()
    },
  })
}

const removeShift = (shiftId: number) => {
  if (confirm('Are you sure you want to remove this shift assignment?')) {
    router.delete(`/employees/${props.employee.id}/shifts/${shiftId}`, {
      preserveScroll: true,
    })
  }
}

const deleteEmployee = () => {
  if (
    confirm(
      `Are you sure you want to delete ${props.employee.full_name}? This action cannot be undone.`
    )
  ) {
    router.delete(employees.destroy(props.employee.id).url)
  }
}

const syncEmployee = () => {
  router.post(`/employees/${props.employee.id}/sync`, {}, {
    preserveScroll: true,
  })
}

const syncToDevice = () => {
  syncForm.post(`/employees/${props.employee.id}/sync-to-device`, {
    preserveScroll: true,
    onSuccess: () => {
      showSyncToDeviceDialog.value = false
      syncForm.reset()
    },
  })
}

const getEnrollmentStatusColor = (status: string) => {
  switch (status) {
    case 'synced':
      return 'default'
    case 'pending':
      return 'secondary'
    case 'failed':
      return 'destructive'
    default:
      return 'secondary'
  }
}

const getEnrollmentStatusIcon = (status: string) => {
  switch (status) {
    case 'synced':
      return CheckCircle
    case 'pending':
      return Clock
    case 'failed':
      return XCircle
    default:
      return AlertCircle
  }
}

const showNotification = ref(false)
const notificationMessage = ref('')
const notificationStatus = ref<'success' | 'error'>('success')
let pollInterval: NodeJS.Timeout | null = null

const checkSyncNotifications = async () => {
  try {
    const response = await fetch(`/employees/${props.employee.id}/sync-notifications`)
    const data = await response.json()

    if (data.notifications && data.notifications.length > 0) {
      data.notifications.forEach((notification: any) => {
        notificationStatus.value = notification.status === 'synced' ? 'success' : 'error'
        notificationMessage.value = notification.message
        showNotification.value = true

        // Hide notification after 5 seconds
        setTimeout(() => {
          showNotification.value = false
        }, 5000)
      })

      // Reload page to update enrollment status
      router.reload({ only: ['employee'] })
    }
  } catch (error) {
    console.error('Failed to check sync notifications:', error)
  }
}

onMounted(() => {
  // Poll for notifications every 2 seconds
  pollInterval = setInterval(checkSyncNotifications, 2000)
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})
</script>

<template>
  <AppLayout>
    <Head :title="employee.full_name" />

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
          :href="employees.index().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Employees
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ employee.full_name }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header with Avatar and Actions -->
      <div class="flex items-start justify-between">
        <div class="flex items-start gap-6">
          <div class="flex-shrink-0">
            <div class="w-28 h-28 rounded-full overflow-hidden bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-white text-3xl font-semibold shadow-xl shadow-blue-500/30 ring-4 ring-background">
              <img
                v-if="employee.avatar_url"
                :src="employee.avatar_url"
                :alt="employee.full_name"
                class="w-full h-full object-cover"
              />
              <span v-else>
                {{ employee.first_name[0] }}{{ employee.last_name[0] }}
              </span>
            </div>
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
                {{ employee.full_name }}
              </h1>
              <Badge v-if="employee.is_active" variant="default" class="text-xs">
                Active
              </Badge>
              <Badge v-else variant="secondary" class="text-xs">
                Inactive
              </Badge>
            </div>
            <p class="text-lg text-muted-foreground mt-2 font-medium">
              {{ employee.custom_id }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="employees.edit(employee.id).url">
            <Button class="gap-2">
              <Edit class="h-4 w-4" />
              Edit
            </Button>
          </Link>
          <Button variant="destructive" @click="deleteEmployee" class="gap-2">
            <Trash2 class="h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <h2 class="text-xl font-semibold mb-6">Contact Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex items-center gap-4 p-4 rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border border-blue-100 dark:border-blue-900">
            <div class="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
              <Mail class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="min-w-0">
              <p class="text-sm text-muted-foreground">Email</p>
              <p class="font-medium truncate">{{ employee.email }}</p>
            </div>
          </div>
          <div class="flex items-center gap-4 p-4 rounded-lg bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-950/20 dark:to-green-950/20 border border-emerald-100 dark:border-emerald-900">
            <div class="h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center flex-shrink-0">
              <Phone class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Phone</p>
              <p class="font-medium">
                {{ employee.phone || 'Not provided' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Employment Details -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <h2 class="text-xl font-semibold mb-6">Employment Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex items-center gap-4 p-4 rounded-lg bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-950/20 dark:to-purple-950/20 border border-violet-100 dark:border-violet-900">
            <div class="h-12 w-12 rounded-full bg-violet-100 dark:bg-violet-900 flex items-center justify-center flex-shrink-0">
              <Building2 class="h-6 w-6 text-violet-600 dark:text-violet-400" />
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Department</p>
              <p class="font-medium">
                {{ employee.department?.name || 'No department' }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-4 p-4 rounded-lg bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-950/20 dark:to-amber-950/20 border border-orange-100 dark:border-orange-900">
            <div class="h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center flex-shrink-0">
              <Calendar class="h-6 w-6 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Hired Date</p>
              <p class="font-medium">
                {{ employee.hired_at ? new Date(employee.hired_at).toLocaleDateString() : 'Not specified' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Shifts -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold">Assigned Shifts</h2>
          <Dialog v-model:open="showAddShiftDialog">
            <DialogTrigger as-child>
              <Button size="sm">
                <Plus class="w-4 h-4 mr-2" />
                Assign Shift
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Assign Shift to {{ employee.full_name }}</DialogTitle>
                <DialogDescription>
                  Assign a shift with an effective date range
                </DialogDescription>
              </DialogHeader>
              <form @submit.prevent="addShift" class="space-y-4">
                <div class="space-y-2">
                  <Label for="shift_id">Shift *</Label>
                  <select
                    id="shift_id"
                    v-model="shiftForm.shift_id"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    required
                  >
                    <option :value="null">Select a shift</option>
                    <option
                      v-for="shift in available_shifts"
                      :key="shift.id"
                      :value="shift.id"
                    >
                      {{ shift.name }} ({{ shift.start_time.slice(0, 5) }} - {{ shift.end_time.slice(0, 5) }})
                    </option>
                  </select>
                  <p v-if="shiftForm.errors.shift_id" class="text-sm text-destructive">
                    {{ shiftForm.errors.shift_id }}
                  </p>
                </div>
                <div class="space-y-2">
                  <Label for="effective_from">Effective From *</Label>
                  <Input
                    id="effective_from"
                    v-model="shiftForm.effective_from"
                    type="date"
                    required
                  />
                  <p v-if="shiftForm.errors.effective_from" class="text-sm text-destructive">
                    {{ shiftForm.errors.effective_from }}
                  </p>
                </div>
                <div class="space-y-2">
                  <Label for="effective_to">Effective To</Label>
                  <Input
                    id="effective_to"
                    v-model="shiftForm.effective_to"
                    type="date"
                  />
                  <p v-if="shiftForm.errors.effective_to" class="text-sm text-destructive">
                    {{ shiftForm.errors.effective_to }}
                  </p>
                  <p class="text-xs text-muted-foreground">
                    Leave empty for ongoing assignment
                  </p>
                </div>
                <div class="flex justify-end gap-2">
                  <Button type="button" variant="outline" @click="showAddShiftDialog = false">
                    Cancel
                  </Button>
                  <Button type="submit" :disabled="shiftForm.processing">
                    Assign Shift
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </div>
        <div v-if="employee.shifts.length > 0" class="space-y-3">
          <div
            v-for="shift in employee.shifts"
            :key="shift.id"
            class="flex items-center justify-between p-4 rounded-lg bg-muted/50"
          >
            <div>
              <p class="font-medium">{{ shift.name }}</p>
              <p class="text-sm text-muted-foreground">
                {{ shift.start_time }} - {{ shift.end_time }}
              </p>
            </div>
            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="text-sm text-muted-foreground">
                  From {{ new Date(shift.pivot.effective_from).toLocaleDateString() }}
                </p>
                <p v-if="shift.pivot.effective_to" class="text-sm text-muted-foreground">
                  To {{ new Date(shift.pivot.effective_to).toLocaleDateString() }}
                </p>
                <Badge v-else variant="default" class="mt-1">Current</Badge>
              </div>
              <Button
                variant="ghost"
                size="sm"
                @click="removeShift(shift.id)"
              >
                <Trash2 class="h-4 w-4 text-destructive" />
              </Button>
            </div>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-center py-8">
          No shifts assigned
        </p>
      </div>

      <!-- Device Enrollments -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold">Device Enrollments</h2>
          <div v-if="employee.is_active" class="flex items-center gap-2">
            <Dialog v-model:open="showSyncToDeviceDialog">
              <DialogTrigger as-child>
                <Button size="sm" variant="outline" class="gap-2">
                  <RefreshCw class="w-4 h-4" />
                  Sync to Device
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Sync {{ employee.full_name }} to Device</DialogTitle>
                  <DialogDescription>
                    Select a device to sync this employee to
                  </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="syncToDevice" class="space-y-4">
                  <div class="space-y-2">
                    <Label for="device_id">Device *</Label>
                    <select
                      id="device_id"
                      v-model="syncForm.device_id"
                      class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      required
                    >
                      <option :value="null">Select a device</option>
                      <option
                        v-for="device in available_devices"
                        :key="device.id"
                        :value="device.id"
                      >
                        {{ device.device_name }} ({{ device.device_id }})
                      </option>
                    </select>
                    <p v-if="syncForm.errors.device_id" class="text-sm text-destructive">
                      {{ syncForm.errors.device_id }}
                    </p>
                  </div>
                  <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="showSyncToDeviceDialog = false">
                      Cancel
                    </Button>
                    <Button type="submit" :disabled="syncForm.processing">
                      <RefreshCw class="w-4 h-4 mr-2" />
                      Sync
                    </Button>
                  </div>
                </form>
              </DialogContent>
            </Dialog>
            <Button
              size="sm"
              variant="outline"
              @click="syncEmployee"
              class="gap-2"
            >
              <RefreshCw class="w-4 h-4" />
              Sync to All
            </Button>
          </div>
        </div>
        <div v-if="employee.device_enrollments.length > 0" class="space-y-3">
          <div
            v-for="enrollment in employee.device_enrollments"
            :key="enrollment.id"
            class="flex items-center justify-between p-4 rounded-lg bg-muted/50"
          >
            <div class="flex items-center gap-3">
              <component
                :is="getEnrollmentStatusIcon(enrollment.enrollment_status)"
                class="h-5 w-5"
                :class="{
                  'text-green-600': enrollment.enrollment_status === 'synced',
                  'text-yellow-600': enrollment.enrollment_status === 'pending',
                  'text-red-600': enrollment.enrollment_status === 'failed',
                }"
              />
              <div>
                <p class="font-medium">{{ enrollment.device.device_name }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ enrollment.device.device_id }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <Badge :variant="getEnrollmentStatusColor(enrollment.enrollment_status)">
                {{ enrollment.enrollment_status }}
              </Badge>
              <p v-if="enrollment.last_synced_at" class="text-xs text-muted-foreground mt-1">
                Last synced: {{ new Date(enrollment.last_synced_at).toLocaleString() }}
              </p>
            </div>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-center py-8">
          Not enrolled in any devices
        </p>
      </div>

      <!-- Recent Attendance -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <h2 class="text-xl font-semibold mb-6">Recent Attendance</h2>
        <div v-if="employee.attendance_records.length > 0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Check In</TableHead>
                <TableHead>Check Out</TableHead>
                <TableHead>Total Hours</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="record in employee.attendance_records" :key="record.id">
                <TableCell>
                  {{ record.date }}
                </TableCell>
                <TableCell>
                  {{ record.check_in ? new Date(record.check_in).toLocaleTimeString() : '-' }}
                </TableCell>
                <TableCell>
                  {{ record.check_out ? new Date(record.check_out).toLocaleTimeString() : '-' }}
                </TableCell>
                <TableCell>
                  {{ record.total_hours ? `${record.total_hours.toFixed(2)}h` : '-' }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
        <p v-else class="text-muted-foreground text-center py-8">
          No attendance records
        </p>
      </div>
    </div>

    <!-- Notification Toast -->
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
        class="fixed top-4 right-4 z-50 flex items-center gap-3 px-6 py-4 rounded-lg shadow-lg border"
        :class="{
          'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800': notificationStatus === 'success',
          'bg-red-50 border-red-200 dark:bg-red-950 dark:border-red-800': notificationStatus === 'error',
        }"
      >
        <CheckCircle
          v-if="notificationStatus === 'success'"
          class="h-5 w-5 text-green-600 dark:text-green-400"
        />
        <XCircle
          v-else
          class="h-5 w-5 text-red-600 dark:text-red-400"
        />
        <div>
          <p
            class="font-medium"
            :class="{
              'text-green-900 dark:text-green-100': notificationStatus === 'success',
              'text-red-900 dark:text-red-100': notificationStatus === 'error',
            }"
          >
            {{ notificationStatus === 'success' ? 'Sync Successful' : 'Sync Failed' }}
          </p>
          <p
            class="text-sm"
            :class="{
              'text-green-700 dark:text-green-300': notificationStatus === 'success',
              'text-red-700 dark:text-red-300': notificationStatus === 'error',
            }"
          >
            {{ notificationMessage }}
          </p>
        </div>
        <button
          @click="showNotification = false"
          class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
        >
          <XCircle class="h-5 w-5" />
        </button>
      </div>
    </Transition>
  </AppLayout>
</template>
