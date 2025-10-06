<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { AlertTriangle, CheckCircle2, MessageSquare, ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface Violation {
  id: number
  date: string
  type: string
  severity: string
  status: string
  description: string
  dispute_reason: string | null
  acknowledged_at: string | null
  disputed_at: string | null
  created_at: string
}

interface PaginatedResponse {
  data: Violation[]
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

const loading = ref(false)
const violations = ref<Violation[]>([])
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0,
  perPage: 20,
})

// Filters
const filters = ref({
  from: '',
  to: '',
  type: '',
  severity: '',
  status: '',
})

// Dispute modal
const showDisputeModal = ref(false)
const disputingViolation = ref<Violation | null>(null)
const disputeReason = ref('')
const disputeSubmitting = ref(false)

const severityColor = (severity: string) => {
  const colors = {
    minor: 'default',
    moderate: 'secondary',
    major: 'destructive',
    critical: 'destructive',
  }
  return colors[severity as keyof typeof colors] || 'default'
}

const statusColor = (status: string) => {
  const colors = {
    pending: 'secondary',
    acknowledged: 'default',
    disputed: 'outline',
    resolved: 'default',
  }
  return colors[status as keyof typeof colors] || 'default'
}

const typeLabel = (type: string) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const loadViolations = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      per_page: String(pagination.value.perPage),
      ...(filters.value.from && { from: filters.value.from }),
      ...(filters.value.to && { to: filters.value.to }),
      ...(filters.value.type && { type: filters.value.type }),
      ...(filters.value.severity && { severity: filters.value.severity }),
      ...(filters.value.status && { status: filters.value.status }),
    })

    const response = await fetch(`/api/v1/employee/violations?${params}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      throw new Error('Failed to load violations')
    }

    const result: { success: boolean; data: Violation[]; meta: any } = await response.json()

    if (result.success) {
      violations.value = result.data
      if (result.meta) {
        pagination.value = {
          currentPage: result.meta.current_page,
          lastPage: result.meta.last_page,
          total: result.meta.total,
          perPage: result.meta.per_page,
        }
      }
    }
  } catch (error) {
    console.error('Error loading violations:', error)
  } finally {
    loading.value = false
  }
}

const acknowledgeViolation = async (violation: Violation) => {
  try {
    const response = await fetch(`/api/v1/employee/violations/${violation.id}/acknowledge`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      throw new Error('Failed to acknowledge violation')
    }

    // Reload violations
    await loadViolations(pagination.value.currentPage)
  } catch (error) {
    console.error('Error acknowledging violation:', error)
    alert('Failed to acknowledge violation. Please try again.')
  }
}

const openDisputeModal = (violation: Violation) => {
  disputingViolation.value = violation
  disputeReason.value = ''
  showDisputeModal.value = true
}

const submitDispute = async () => {
  if (!disputingViolation.value || disputeReason.value.length < 10) {
    alert('Please provide a reason with at least 10 characters')
    return
  }

  disputeSubmitting.value = true
  try {
    const response = await fetch(`/api/v1/employee/violations/${disputingViolation.value.id}/dispute`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: JSON.stringify({
        reason: disputeReason.value,
      }),
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Failed to dispute violation')
    }

    // Close modal and reload violations
    showDisputeModal.value = false
    await loadViolations(pagination.value.currentPage)
  } catch (error) {
    console.error('Error disputing violation:', error)
    alert(error instanceof Error ? error.message : 'Failed to dispute violation. Please try again.')
  } finally {
    disputeSubmitting.value = false
  }
}

const applyFilters = () => {
  loadViolations(1)
}

const clearFilters = () => {
  filters.value = {
    from: '',
    to: '',
    type: '',
    severity: '',
    status: '',
  }
  loadViolations(1)
}

const previousPage = () => {
  if (pagination.value.currentPage > 1) {
    loadViolations(pagination.value.currentPage - 1)
  }
}

const nextPage = () => {
  if (pagination.value.currentPage < pagination.value.lastPage) {
    loadViolations(pagination.value.currentPage + 1)
  }
}

onMounted(() => {
  loadViolations()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Filters Card -->
    <Card>
      <CardHeader>
        <CardTitle>Filters</CardTitle>
        <CardDescription>Filter violations by date range, type, severity, or status</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
          <div class="space-y-2">
            <Label for="from">From Date</Label>
            <Input
              id="from"
              v-model="filters.from"
              type="date"
            />
          </div>
          <div class="space-y-2">
            <Label for="to">To Date</Label>
            <Input
              id="to"
              v-model="filters.to"
              type="date"
            />
          </div>
          <div class="space-y-2">
            <Label for="type">Type</Label>
            <Select v-model="filters.type">
              <SelectTrigger id="type">
                <SelectValue placeholder="All types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All types</SelectItem>
                <SelectItem value="late_arrival">Late Arrival</SelectItem>
                <SelectItem value="early_departure">Early Departure</SelectItem>
                <SelectItem value="extended_break">Extended Break</SelectItem>
                <SelectItem value="missing_checkout">Missing Checkout</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="severity">Severity</Label>
            <Select v-model="filters.severity">
              <SelectTrigger id="severity">
                <SelectValue placeholder="All severities" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All severities</SelectItem>
                <SelectItem value="minor">Minor</SelectItem>
                <SelectItem value="moderate">Moderate</SelectItem>
                <SelectItem value="major">Major</SelectItem>
                <SelectItem value="critical">Critical</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="status">Status</Label>
            <Select v-model="filters.status">
              <SelectTrigger id="status">
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All statuses</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="acknowledged">Acknowledged</SelectItem>
                <SelectItem value="disputed">Disputed</SelectItem>
                <SelectItem value="resolved">Resolved</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <Button @click="applyFilters" :disabled="loading">
            Apply Filters
          </Button>
          <Button variant="outline" @click="clearFilters" :disabled="loading">
            Clear Filters
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Violations List Card -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>Violations</CardTitle>
            <CardDescription>{{ pagination.total }} total violations found</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="text-muted-foreground">Loading violations...</div>
        </div>

        <div v-else-if="violations.length === 0" class="text-center py-12 text-muted-foreground">
          No violations found
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="violation in violations"
            :key="violation.id"
            class="border rounded-lg p-4 space-y-3"
          >
            <div class="flex items-start justify-between">
              <div class="space-y-2 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <Badge :variant="severityColor(violation.severity)">
                    {{ violation.severity }}
                  </Badge>
                  <Badge variant="outline">{{ typeLabel(violation.type) }}</Badge>
                  <Badge :variant="statusColor(violation.status)">{{ violation.status }}</Badge>
                  <span class="text-sm text-muted-foreground">{{ new Date(violation.date).toLocaleDateString() }}</span>
                </div>
                <p class="text-sm">{{ violation.description }}</p>
                <div v-if="violation.dispute_reason" class="bg-muted p-3 rounded text-sm">
                  <div class="font-semibold mb-1">Dispute Reason:</div>
                  {{ violation.dispute_reason }}
                </div>
              </div>
              <div class="flex gap-2 ml-4">
                <Button
                  v-if="violation.status === 'pending'"
                  size="sm"
                  variant="outline"
                  @click="acknowledgeViolation(violation)"
                >
                  <CheckCircle2 class="h-4 w-4 mr-1" />
                  Acknowledge
                </Button>
                <Button
                  v-if="violation.status === 'pending'"
                  size="sm"
                  variant="destructive"
                  @click="openDisputeModal(violation)"
                >
                  <MessageSquare class="h-4 w-4 mr-1" />
                  Dispute
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.lastPage > 1" class="flex items-center justify-between mt-6 pt-6 border-t">
          <div class="text-sm text-muted-foreground">
            Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
            ({{ pagination.total }} total)
          </div>
          <div class="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              @click="previousPage"
              :disabled="pagination.currentPage === 1 || loading"
            >
              <ChevronLeft class="h-4 w-4 mr-1" />
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              @click="nextPage"
              :disabled="pagination.currentPage === pagination.lastPage || loading"
            >
              Next
              <ChevronRight class="h-4 w-4 ml-1" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Dispute Modal -->
    <Dialog v-model:open="showDisputeModal">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Dispute Violation</DialogTitle>
          <DialogDescription>
            Provide a detailed reason for disputing this violation. Your manager will review it.
          </DialogDescription>
        </DialogHeader>

        <div v-if="disputingViolation" class="space-y-4">
          <div class="bg-muted p-3 rounded">
            <div class="font-semibold">{{ typeLabel(disputingViolation.type) }}</div>
            <div class="text-sm text-muted-foreground">{{ new Date(disputingViolation.date).toLocaleDateString() }}</div>
            <p class="text-sm mt-2">{{ disputingViolation.description }}</p>
          </div>

          <div class="space-y-2">
            <Label for="dispute-reason">Reason for Dispute *</Label>
            <Textarea
              id="dispute-reason"
              v-model="disputeReason"
              placeholder="Explain why you believe this violation is incorrect (minimum 10 characters)..."
              rows="4"
              :class="{ 'border-destructive': disputeReason.length > 0 && disputeReason.length < 10 }"
            />
            <p class="text-xs text-muted-foreground">
              {{ disputeReason.length }} / 10 characters minimum
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showDisputeModal = false" :disabled="disputeSubmitting">
            Cancel
          </Button>
          <Button @click="submitDispute" :disabled="disputeSubmitting || disputeReason.length < 10">
            {{ disputeSubmitting ? 'Submitting...' : 'Submit Dispute' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
