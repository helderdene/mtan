<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Card, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Calendar, MapPin, User, Eye } from 'lucide-vue-next'
import { computed } from 'vue'

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
  log: StrangerLog
  selected: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  toggleSelect: []
  viewPhoto: []
}>()

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
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Card
    :class="[
      'cursor-pointer transition-all hover:shadow-lg',
      selected && 'ring-2 ring-primary',
    ]"
  >
    <CardContent class="p-0">
      <!-- Photo Thumbnail -->
      <div class="relative aspect-[4/3] bg-muted">
        <img
          :src="log.photo_url"
          :alt="`Stranger detection ${log.id}`"
          class="w-full h-full object-cover"
          @error="$event.target.src = 'https://placehold.co/400x300?text=Photo+Unavailable'"
        />

        <!-- Selection Checkbox -->
        <div class="absolute top-2 left-2">
          <Checkbox
            :checked="selected"
            @update:checked="emit('toggleSelect')"
            class="bg-background/80 backdrop-blur-sm"
          />
        </div>

        <!-- Status Badge -->
        <div class="absolute top-2 right-2">
          <Badge :variant="statusBadgeVariant">
            {{ statusLabel }}
          </Badge>
        </div>

        <!-- View Photo Button -->
        <div class="absolute inset-0 bg-black/0 hover:bg-black/40 transition-colors flex items-center justify-center opacity-0 hover:opacity-100">
          <Button
            @click="emit('viewPhoto')"
            variant="secondary"
            size="sm"
          >
            <Eye class="h-4 w-4 mr-2" />
            View Full Size
          </Button>
        </div>
      </div>

      <!-- Card Info -->
      <div class="p-4 space-y-2">
        <!-- Device -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <MapPin class="h-4 w-4" />
          <span>{{ log.device?.name || 'Unknown Device' }}</span>
        </div>

        <!-- Detected Time -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <Calendar class="h-4 w-4" />
          <span>{{ formatDateTime(log.detected_at) }}</span>
        </div>

        <!-- Matched Employee (if matched) -->
        <div v-if="log.employee" class="flex items-center gap-2 text-sm">
          <User class="h-4 w-4 text-primary" />
          <span class="font-medium">{{ log.employee.full_name }}</span>
          <Badge variant="outline" class="text-xs">{{ log.employee.custom_id }}</Badge>
        </div>

        <!-- Notes (if any) -->
        <div v-if="log.notes" class="text-sm text-muted-foreground line-clamp-2 mt-2 pt-2 border-t">
          {{ log.notes }}
        </div>

        <!-- Matched By (if matched) -->
        <div v-if="log.matched_by" class="text-xs text-muted-foreground mt-2 pt-2 border-t">
          Matched by {{ log.matched_by.name }}
        </div>
      </div>
    </CardContent>
  </Card>
</template>
