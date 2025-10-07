<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Calendar, MapPin, User, ZoomIn, ZoomOut, RotateCw } from 'lucide-vue-next'
import { ref, computed } from 'vue'

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
  open: boolean
  log: StrangerLog
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const zoom = ref(100)
const rotation = ref(0)

const statusBadgeVariant = computed(() => {
  switch (props.log.match_status) {
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
  switch (props.log.match_status) {
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

const zoomIn = () => {
  if (zoom.value < 200) zoom.value += 25
}

const zoomOut = () => {
  if (zoom.value > 50) zoom.value -= 25
}

const rotate = () => {
  rotation.value = (rotation.value + 90) % 360
}

const resetView = () => {
  zoom.value = 100
  rotation.value = 0
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('close')">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle class="flex items-center justify-between">
          <span>Stranger Detection #{{ log.id }}</span>
          <Badge :variant="statusBadgeVariant">
            {{ statusLabel }}
          </Badge>
        </DialogTitle>
      </DialogHeader>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Photo Viewer (Left - 2 columns) -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Zoom Controls -->
          <div class="flex items-center justify-center gap-2">
            <Button @click="zoomOut" variant="outline" size="sm" :disabled="zoom <= 50">
              <ZoomOut class="h-4 w-4" />
            </Button>
            <span class="text-sm font-medium min-w-[60px] text-center">{{ zoom }}%</span>
            <Button @click="zoomIn" variant="outline" size="sm" :disabled="zoom >= 200">
              <ZoomIn class="h-4 w-4" />
            </Button>
            <Button @click="rotate" variant="outline" size="sm">
              <RotateCw class="h-4 w-4" />
            </Button>
            <Button @click="resetView" variant="outline" size="sm">
              Reset
            </Button>
          </div>

          <!-- Photo Container -->
          <div class="border rounded-lg overflow-hidden bg-muted flex items-center justify-center min-h-[400px]">
            <img
              :src="log.photo_url"
              :alt="`Stranger detection ${log.id}`"
              :style="{
                transform: `scale(${zoom / 100}) rotate(${rotation}deg)`,
                transition: 'transform 0.2s ease-in-out',
              }"
              class="max-w-full h-auto"
              @error="$event.target.src = 'https://placehold.co/800x600?text=Photo+Unavailable'"
            />
          </div>
        </div>

        <!-- Details (Right - 1 column) -->
        <div class="space-y-4">
          <div>
            <h3 class="font-semibold mb-3">Detection Details</h3>
            <div class="space-y-3">
              <!-- Device -->
              <div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                  <MapPin class="h-4 w-4" />
                  <span class="font-medium">Device</span>
                </div>
                <div class="pl-6 text-sm">
                  {{ log.device?.name || 'Unknown Device' }}
                  <Badge variant="outline" class="ml-2">{{ log.device?.device_id }}</Badge>
                </div>
              </div>

              <!-- Detected Time -->
              <div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                  <Calendar class="h-4 w-4" />
                  <span class="font-medium">Detected At</span>
                </div>
                <div class="pl-6 text-sm">
                  {{ formatDateTime(log.detected_at) }}
                </div>
              </div>

              <!-- Matched Employee (if matched) -->
              <div v-if="log.employee">
                <div class="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                  <User class="h-4 w-4" />
                  <span class="font-medium">Matched Employee</span>
                </div>
                <div class="pl-6 text-sm">
                  <div class="font-medium">{{ log.employee.full_name }}</div>
                  <Badge variant="outline" class="mt-1">{{ log.employee.custom_id }}</Badge>
                </div>
              </div>

              <!-- Matched By (if matched) -->
              <div v-if="log.matched_by">
                <div class="text-sm text-muted-foreground mb-1">
                  <span class="font-medium">Matched By</span>
                </div>
                <div class="pl-6 text-sm">
                  {{ log.matched_by.name }}
                  <div class="text-xs text-muted-foreground">{{ log.matched_by.email }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes (if any) -->
          <div v-if="log.notes">
            <h3 class="font-semibold mb-2">Notes</h3>
            <div class="text-sm text-muted-foreground bg-muted p-3 rounded-md">
              {{ log.notes }}
            </div>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
