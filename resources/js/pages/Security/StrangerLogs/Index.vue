<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Filter, X, Trash2, UserCheck, AlertTriangle } from 'lucide-vue-next'
import StrangerLogCard from './StrangerLogCard.vue'
import StrangerPhotoViewer from './StrangerPhotoViewer.vue'
import EmployeeMatchSelector from './EmployeeMatchSelector.vue'

interface Device {
  id: number
  device_id: string
  name: string
}

interface Employee {
  id: number
  custom_id: string
  full_name: string
}

interface StrangerLog {
  id: number
  device_id: number
  employee_id: number | null
  detected_at: string
  photo_url: string
  match_status: 'unreviewed' | 'matched' | 'security_issue'
  notes: string | null
  device?: Device
  employee?: Employee
  matched_by?: {
    name: string
    email: string
  }
}

interface Props {
  strangerLogs: {
    data: StrangerLog[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  devices: Device[]
  filters: {
    match_status?: string
    device_id?: number
    from?: string
    to?: string
  }
}

const props = defineProps<Props>()

// Filter state
const filterOpen = ref(false)
const selectedStatus = ref(props.filters.match_status || '')
const selectedDevice = ref(props.filters.device_id?.toString() || '')
const dateFrom = ref(props.filters.from || '')
const dateTo = ref(props.filters.to || '')

// Selection state
const selectedLogs = ref<number[]>([])
const selectAll = ref(false)

// Modal state
const viewerOpen = ref(false)
const matchSelectorOpen = ref(false)
const currentLog = ref<StrangerLog | null>(null)

// Computed
const hasActiveFilters = computed(() => {
  return selectedStatus.value || selectedDevice.value || dateFrom.value || dateTo.value
})

const bulkActionsVisible = computed(() => selectedLogs.value.length > 0)

// Methods
const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedLogs.value = props.strangerLogs.data.map(log => log.id)
  } else {
    selectedLogs.value = []
  }
}

const toggleSelect = (logId: number) => {
  const index = selectedLogs.value.indexOf(logId)
  if (index > -1) {
    selectedLogs.value.splice(index, 1)
  } else {
    selectedLogs.value.push(logId)
  }
  selectAll.value = selectedLogs.value.length === props.strangerLogs.data.length
}

const applyFilters = () => {
  const params: Record<string, any> = {}
  if (selectedStatus.value) params.match_status = selectedStatus.value
  if (selectedDevice.value) params.device_id = selectedDevice.value
  if (dateFrom.value) params.from = dateFrom.value
  if (dateTo.value) params.to = dateTo.value

  router.get('/security/stranger-logs', params, {
    preserveState: true,
    preserveScroll: true,
  })
  filterOpen.value = false
}

const clearFilters = () => {
  selectedStatus.value = ''
  selectedDevice.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  router.get('/security/stranger-logs', {}, {
    preserveState: true,
    preserveScroll: true,
  })
  filterOpen.value = false
}

const openPhotoViewer = (log: StrangerLog) => {
  currentLog.value = log
  viewerOpen.value = true
}

const openMatchSelector = () => {
  if (selectedLogs.value.length === 0) return
  matchSelectorOpen.value = true
}

const handleBulkMatch = async (employeeId: number, notes: string) => {
  try {
    await fetch('/api/v1/stranger-logs/bulk-process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        action: 'match',
        stranger_log_ids: selectedLogs.value,
        employee_id: employeeId,
        notes: notes,
      }),
    })

    // Reload page data
    router.reload({ only: ['strangerLogs'] })
    selectedLogs.value = []
    selectAll.value = false
    matchSelectorOpen.value = false
  } catch (error) {
    console.error('Bulk match failed:', error)
  }
}

const handleBulkSecurityIssue = async () => {
  const notes = prompt('Enter security issue notes (minimum 10 characters):')
  if (!notes || notes.length < 10) {
    alert('Notes must be at least 10 characters')
    return
  }

  try {
    await fetch('/api/v1/stranger-logs/bulk-process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        action: 'mark-security-issue',
        stranger_log_ids: selectedLogs.value,
        notes: notes,
      }),
    })

    router.reload({ only: ['strangerLogs'] })
    selectedLogs.value = []
    selectAll.value = false
  } catch (error) {
    console.error('Bulk security issue failed:', error)
  }
}

const handleBulkDelete = async () => {
  if (!confirm(`Delete ${selectedLogs.value.length} stranger logs? This cannot be undone.`)) {
    return
  }

  try {
    await fetch('/api/v1/stranger-logs/bulk-process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        action: 'delete',
        stranger_log_ids: selectedLogs.value,
      }),
    })

    router.reload({ only: ['strangerLogs'] })
    selectedLogs.value = []
    selectAll.value = false
  } catch (error) {
    console.error('Bulk delete failed:', error)
  }
}

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case 'unreviewed':
      return 'secondary'
    case 'matched':
      return 'default'
    case 'security_issue':
      return 'destructive'
    default:
      return 'secondary'
  }
}
</script>

<template>
  <Head title="Stranger Logs" />

  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Stranger Logs</h1>
          <p class="text-sm text-muted-foreground">Review unrecognized face detections</p>
        </div>
        <div class="flex items-center gap-2">
          <Badge variant="outline">
            {{ strangerLogs.total }} Total
          </Badge>
          <Button @click="filterOpen = !filterOpen" variant="outline" size="sm">
            <Filter class="h-4 w-4 mr-2" />
            Filters
            <Badge v-if="hasActiveFilters" variant="secondary" class="ml-2">
              Active
            </Badge>
          </Button>
        </div>
      </div>
    </template>

    <!-- Filter Panel -->
    <div v-if="filterOpen" class="mb-6 p-4 border rounded-lg bg-card">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div>
          <label class="text-sm font-medium mb-2 block">Match Status</label>
          <select
            v-model="selectedStatus"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option value="">All statuses</option>
            <option value="unreviewed">Unreviewed</option>
            <option value="matched">Matched</option>
            <option value="security_issue">Security Issue</option>
          </select>
        </div>

        <div>
          <label class="text-sm font-medium mb-2 block">Device</label>
          <select
            v-model="selectedDevice"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option value="">All devices</option>
            <option v-for="device in devices" :key="device.id" :value="device.id.toString()">
              {{ device.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="text-sm font-medium mb-2 block">From Date</label>
          <Input type="date" v-model="dateFrom" />
        </div>

        <div>
          <label class="text-sm font-medium mb-2 block">To Date</label>
          <Input type="date" v-model="dateTo" />
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Button @click="clearFilters" variant="outline" size="sm">
          <X class="h-4 w-4 mr-2" />
          Clear
        </Button>
        <Button @click="applyFilters" size="sm">
          Apply Filters
        </Button>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
    >
      <div v-if="bulkActionsVisible" class="mb-4 p-4 bg-primary/10 border border-primary/20 rounded-lg flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Checkbox :checked="selectAll" @update:checked="selectAll = $event; toggleSelectAll()" />
          <span class="font-medium">{{ selectedLogs.length }} selected</span>
        </div>
        <div class="flex items-center gap-2">
          <Button @click="openMatchSelector" variant="default" size="sm">
            <UserCheck class="h-4 w-4 mr-2" />
            Match to Employee
          </Button>
          <Button @click="handleBulkSecurityIssue" variant="outline" size="sm">
            <AlertTriangle class="h-4 w-4 mr-2" />
            Mark Security Issue
          </Button>
          <Button @click="handleBulkDelete" variant="destructive" size="sm">
            <Trash2 class="h-4 w-4 mr-2" />
            Delete
          </Button>
        </div>
      </div>
    </Transition>

    <!-- Stranger Logs Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <StrangerLogCard
        v-for="log in strangerLogs.data"
        :key="log.id"
        :log="log"
        :selected="selectedLogs.includes(log.id)"
        @toggle-select="toggleSelect(log.id)"
        @view-photo="openPhotoViewer(log)"
      />
    </div>

    <!-- Empty State -->
    <div v-if="strangerLogs.data.length === 0" class="text-center py-12">
      <p class="text-muted-foreground">No stranger logs found</p>
      <Button v-if="hasActiveFilters" @click="clearFilters" variant="outline" size="sm" class="mt-4">
        Clear Filters
      </Button>
    </div>

    <!-- Pagination -->
    <div v-if="strangerLogs.last_page > 1" class="mt-6 flex justify-center gap-2">
      <Button
        v-for="page in strangerLogs.last_page"
        :key="page"
        :variant="page === strangerLogs.current_page ? 'default' : 'outline'"
        size="sm"
        @click="router.get(`/security/stranger-logs?page=${page}`)"
      >
        {{ page }}
      </Button>
    </div>

    <!-- Photo Viewer Modal -->
    <StrangerPhotoViewer
      v-if="currentLog"
      :open="viewerOpen"
      :log="currentLog"
      @close="viewerOpen = false"
    />

    <!-- Employee Match Selector Modal -->
    <EmployeeMatchSelector
      :open="matchSelectorOpen"
      :selected-count="selectedLogs.length"
      @close="matchSelectorOpen = false"
      @confirm="handleBulkMatch"
    />
  </AppLayout>
</template>
