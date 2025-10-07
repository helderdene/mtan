<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { ArrowLeft, User, Search, CheckCircle, AlertTriangle, Calendar, MapPin } from 'lucide-vue-next'

interface Device {
  id: number
  device_id: string
  name: string
}

interface Employee {
  id: number
  custom_id: string
  full_name: string
  photo_path?: string
  department?: {
    name: string
  }
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
  strangerLog: StrangerLog
}

const props = defineProps<Props>()

// Search state
const searchQuery = ref('')
const searchResults = ref<Employee[]>([])
const selectedEmployee = ref<Employee | null>(props.strangerLog.employee || null)
const isSearching = ref(false)

// Form state
const notes = ref(props.strangerLog.notes || '')
const isSubmitting = ref(false)

const statusBadgeVariant = computed(() => {
  switch (props.strangerLog.match_status) {
    case 'unreviewed':
      return 'secondary'
    case 'matched':
      return 'default'
    case 'security_issue':
      return 'destructive'
    default:
      return 'secondary'
  }
})

const statusLabel = computed(() => {
  switch (props.strangerLog.match_status) {
    case 'unreviewed':
      return 'Unreviewed'
    case 'matched':
      return 'Matched'
    case 'security_issue':
      return 'Security Issue'
    default:
      return 'Unknown'
  }
})

const formatDateTime = (timestamp: string) => {
  const date = new Date(timestamp)
  return date.toLocaleString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const searchEmployees = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }

  isSearching.value = true
  try {
    const response = await fetch(`/api/v1/employees/search?q=${encodeURIComponent(searchQuery.value)}`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })
    const data = await response.json()
    searchResults.value = data.data || []
  } catch (error) {
    console.error('Employee search failed:', error)
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

const selectEmployee = (employee: Employee) => {
  selectedEmployee.value = employee
  searchQuery.value = ''
  searchResults.value = []
}

const confirmMatch = async () => {
  if (!selectedEmployee.value) {
    alert('Please select an employee to match')
    return
  }

  if (notes.value.length > 1000) {
    alert('Notes must be less than 1000 characters')
    return
  }

  isSubmitting.value = true
  try {
    await fetch(`/api/v1/stranger-logs/${props.strangerLog.id}/match`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        employee_id: selectedEmployee.value.id,
        notes: notes.value || null,
      }),
    })

    // Redirect back to index with success message
    router.visit('/security/stranger-logs', {
      method: 'get',
      data: { success: 'Stranger log matched successfully' },
    })
  } catch (error) {
    console.error('Match failed:', error)
    alert('Failed to match stranger log. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}

const markSecurityIssue = async () => {
  if (!notes.value || notes.value.length < 10) {
    alert('Notes are required and must be at least 10 characters')
    return
  }

  if (notes.value.length > 1000) {
    alert('Notes must be less than 1000 characters')
    return
  }

  if (!confirm('Mark this as a security issue?')) {
    return
  }

  isSubmitting.value = true
  try {
    await fetch(`/api/v1/stranger-logs/${props.strangerLog.id}/mark-security-issue`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        notes: notes.value,
      }),
    })

    // Redirect back to index with success message
    router.visit('/security/stranger-logs', {
      method: 'get',
      data: { success: 'Marked as security issue successfully' },
    })
  } catch (error) {
    console.error('Mark security issue failed:', error)
    alert('Failed to mark as security issue. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}

const goBack = () => {
  router.visit('/security/stranger-logs')
}
</script>

<template>
  <Head :title="`Stranger Log #${strangerLog.id}`" />

  <AppLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button @click="goBack" variant="ghost" size="sm">
          <ArrowLeft class="h-4 w-4 mr-2" />
          Back to List
        </Button>
        <div class="flex-1">
          <h1 class="text-2xl font-bold">Stranger Log #{{ strangerLog.id }}</h1>
          <p class="text-sm text-muted-foreground">Review and match unrecognized detection</p>
        </div>
        <Badge :variant="statusBadgeVariant">
          {{ statusLabel }}
        </Badge>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left Column: Stranger Photo & Details -->
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Detection Photo</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="aspect-[4/3] bg-muted rounded-lg overflow-hidden">
              <img
                :src="strangerLog.photo_url"
                :alt="`Stranger detection ${strangerLog.id}`"
                class="w-full h-full object-contain"
                @error="$event.target.src = 'https://placehold.co/800x600?text=Photo+Unavailable'"
              />
            </div>

            <!-- Detection Info -->
            <div class="mt-4 space-y-3">
              <div class="flex items-center gap-2 text-sm">
                <MapPin class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium">Device:</span>
                <span>{{ strangerLog.device?.name || 'Unknown Device' }}</span>
                <Badge variant="outline" class="text-xs">{{ strangerLog.device?.device_id }}</Badge>
              </div>

              <div class="flex items-center gap-2 text-sm">
                <Calendar class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium">Detected:</span>
                <span>{{ formatDateTime(strangerLog.detected_at) }}</span>
              </div>

              <div v-if="strangerLog.matched_by" class="flex items-center gap-2 text-sm">
                <User class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium">Matched by:</span>
                <span>{{ strangerLog.matched_by.name }}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Right Column: Employee Search & Comparison -->
      <div class="space-y-6">
        <!-- Employee Search -->
        <Card v-if="strangerLog.match_status === 'unreviewed'">
          <CardHeader>
            <CardTitle>Search Employee</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="employee-search">Search by name or employee ID</Label>
              <div class="relative">
                <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  id="employee-search"
                  v-model="searchQuery"
                  @input="searchEmployees"
                  placeholder="Start typing to search..."
                  class="pl-9"
                />
              </div>

              <!-- Search Results -->
              <div
                v-if="searchResults.length > 0"
                class="border rounded-md max-h-60 overflow-y-auto"
              >
                <button
                  v-for="employee in searchResults"
                  :key="employee.id"
                  @click="selectEmployee(employee)"
                  class="w-full px-4 py-3 text-left hover:bg-accent transition-colors flex items-center justify-between border-b last:border-b-0"
                >
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-muted flex items-center justify-center overflow-hidden">
                      <img
                        v-if="employee.photo_path"
                        :src="employee.photo_path"
                        :alt="employee.full_name"
                        class="w-full h-full object-cover"
                      />
                      <User v-else class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                      <div class="font-medium">{{ employee.full_name }}</div>
                      <div class="text-sm text-muted-foreground">{{ employee.custom_id }}</div>
                    </div>
                  </div>
                  <Badge v-if="employee.department" variant="outline">
                    {{ employee.department.name }}
                  </Badge>
                </button>
              </div>

              <!-- No Results -->
              <div
                v-if="searchQuery.length >= 2 && searchResults.length === 0 && !isSearching"
                class="text-sm text-muted-foreground text-center py-4 border rounded-md"
              >
                No employees found
              </div>

              <!-- Searching -->
              <div
                v-if="isSearching"
                class="text-sm text-muted-foreground text-center py-4 border rounded-md"
              >
                Searching...
              </div>
            </div>

            <!-- Selected Employee - Side by Side Comparison -->
            <div v-if="selectedEmployee" class="border-t pt-4">
              <h3 class="font-semibold mb-3">Photo Comparison</h3>
              <div class="grid grid-cols-2 gap-4">
                <!-- Stranger Photo (Small) -->
                <div>
                  <p class="text-xs text-muted-foreground mb-2">Detected Photo</p>
                  <div class="aspect-square bg-muted rounded-lg overflow-hidden">
                    <img
                      :src="strangerLog.photo_url"
                      alt="Detected photo"
                      class="w-full h-full object-cover"
                    />
                  </div>
                </div>

                <!-- Employee Photo -->
                <div>
                  <p class="text-xs text-muted-foreground mb-2">Employee Photo</p>
                  <div class="aspect-square bg-muted rounded-lg overflow-hidden flex items-center justify-center">
                    <img
                      v-if="selectedEmployee.photo_path"
                      :src="selectedEmployee.photo_path"
                      :alt="selectedEmployee.full_name"
                      class="w-full h-full object-cover"
                    />
                    <User v-else class="h-12 w-12 text-muted-foreground" />
                  </div>
                </div>
              </div>

              <div class="mt-4 p-3 bg-muted/50 rounded-md">
                <div class="font-medium">{{ selectedEmployee.full_name }}</div>
                <div class="text-sm text-muted-foreground">{{ selectedEmployee.custom_id }}</div>
                <Badge v-if="selectedEmployee.department" variant="outline" class="mt-2">
                  {{ selectedEmployee.department.name }}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Already Matched Display -->
        <Card v-else-if="strangerLog.employee">
          <CardHeader>
            <CardTitle>Matched Employee</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <!-- Stranger Photo (Small) -->
                <div>
                  <p class="text-xs text-muted-foreground mb-2">Detected Photo</p>
                  <div class="aspect-square bg-muted rounded-lg overflow-hidden">
                    <img
                      :src="strangerLog.photo_url"
                      alt="Detected photo"
                      class="w-full h-full object-cover"
                    />
                  </div>
                </div>

                <!-- Employee Photo -->
                <div>
                  <p class="text-xs text-muted-foreground mb-2">Employee Photo</p>
                  <div class="aspect-square bg-muted rounded-lg overflow-hidden flex items-center justify-center">
                    <img
                      v-if="strangerLog.employee.photo_path"
                      :src="strangerLog.employee.photo_path"
                      :alt="strangerLog.employee.full_name"
                      class="w-full h-full object-cover"
                    />
                    <User v-else class="h-12 w-12 text-muted-foreground" />
                  </div>
                </div>
              </div>

              <div class="p-3 bg-muted/50 rounded-md">
                <div class="font-medium">{{ strangerLog.employee.full_name }}</div>
                <div class="text-sm text-muted-foreground">{{ strangerLog.employee.custom_id }}</div>
                <Badge v-if="strangerLog.employee.department" variant="outline" class="mt-2">
                  {{ strangerLog.employee.department.name }}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Notes & Actions -->
        <Card>
          <CardHeader>
            <CardTitle>Notes & Actions</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="notes">Notes {{ strangerLog.match_status === 'unreviewed' ? '(Optional)' : '' }}</Label>
              <textarea
                id="notes"
                v-model="notes"
                :disabled="strangerLog.match_status !== 'unreviewed'"
                placeholder="Add any notes about this detection or match..."
                rows="4"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
              />
              <p class="text-xs text-muted-foreground">
                For security issues, notes must be at least 10 characters
              </p>
            </div>

            <!-- Action Buttons -->
            <div v-if="strangerLog.match_status === 'unreviewed'" class="flex gap-2">
              <Button
                @click="confirmMatch"
                :disabled="!selectedEmployee || isSubmitting"
                class="flex-1"
              >
                <CheckCircle class="h-4 w-4 mr-2" />
                Confirm Match
              </Button>
              <Button
                @click="markSecurityIssue"
                :disabled="isSubmitting"
                variant="destructive"
              >
                <AlertTriangle class="h-4 w-4 mr-2" />
                Security Issue
              </Button>
            </div>

            <!-- Status Display -->
            <div v-else class="p-4 bg-muted rounded-md text-center">
              <p class="text-sm text-muted-foreground">
                This stranger log has already been {{ strangerLog.match_status === 'matched' ? 'matched' : 'marked as a security issue' }}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
