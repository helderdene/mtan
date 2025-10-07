<script setup lang="ts">
import { ref, computed } from 'vue'
import { GripVertical, Plus, Trash2, Clock } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  color_code?: string
}

interface SequenceItem {
  shift_id: number
  duration_days: number
  shift?: Shift
}

interface Props {
  modelValue: SequenceItem[]
  availableShifts: Shift[]
}

interface Emits {
  (e: 'update:modelValue', value: SequenceItem[]): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const draggedIndex = ref<number | null>(null)

const sequence = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const addSequenceItem = () => {
  if (props.availableShifts.length === 0) return

  const newItem: SequenceItem = {
    shift_id: props.availableShifts[0].id,
    duration_days: 7,
    shift: props.availableShifts[0]
  }

  sequence.value = [...sequence.value, newItem]
}

const removeSequenceItem = (index: number) => {
  sequence.value = sequence.value.filter((_, i) => i !== index)
}

const updateShift = (index: number, shiftId: number) => {
  const shift = props.availableShifts.find(s => s.id === shiftId)
  const updated = [...sequence.value]
  updated[index] = {
    ...updated[index],
    shift_id: shiftId,
    shift
  }
  sequence.value = updated
}

const updateDuration = (index: number, days: number) => {
  const updated = [...sequence.value]
  updated[index] = {
    ...updated[index],
    duration_days: Math.max(1, days)
  }
  sequence.value = updated
}

const onDragStart = (index: number) => {
  draggedIndex.value = index
}

const onDragOver = (event: DragEvent) => {
  event.preventDefault()
}

const onDrop = (index: number) => {
  if (draggedIndex.value === null) return

  const updated = [...sequence.value]
  const draggedItem = updated[draggedIndex.value]
  updated.splice(draggedIndex.value, 1)
  updated.splice(index, 0, draggedItem)

  sequence.value = updated
  draggedIndex.value = null
}

const totalDays = computed(() => {
  return sequence.value.reduce((sum, item) => sum + item.duration_days, 0)
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-medium">Rotation Sequence</h3>
        <p class="text-xs text-muted-foreground mt-1">
          Drag to reorder • Total cycle: {{ totalDays }} days
        </p>
      </div>
      <Button
        type="button"
        size="sm"
        variant="outline"
        @click="addSequenceItem"
        :disabled="availableShifts.length === 0"
      >
        <Plus class="h-4 w-4" />
        Add Shift
      </Button>
    </div>

    <!-- Empty State -->
    <div
      v-if="sequence.length === 0"
      class="border-2 border-dashed rounded-lg p-8 text-center"
    >
      <Clock class="h-12 w-12 mx-auto text-muted-foreground/50 mb-3" />
      <p class="text-sm font-medium text-muted-foreground">No shifts in rotation</p>
      <p class="text-xs text-muted-foreground mt-1">Click "Add Shift" to start building your rotation sequence</p>
    </div>

    <!-- Sequence Items -->
    <div v-else class="space-y-2">
      <div
        v-for="(item, index) in sequence"
        :key="index"
        class="group relative flex items-center gap-3 p-4 rounded-lg border bg-card transition-all hover:shadow-md"
        :class="{
          'opacity-50': draggedIndex === index
        }"
        draggable="true"
        @dragstart="onDragStart(index)"
        @dragover="onDragOver"
        @drop="onDrop(index)"
      >
        <!-- Drag Handle -->
        <div class="cursor-grab active:cursor-grabbing text-muted-foreground/50 group-hover:text-muted-foreground">
          <GripVertical class="h-5 w-5" />
        </div>

        <!-- Position -->
        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-sm font-semibold">
          {{ index + 1 }}
        </div>

        <!-- Shift Selector -->
        <div class="flex-1 grid grid-cols-2 gap-4">
          <div>
            <Label class="text-xs text-muted-foreground mb-1">Shift</Label>
            <select
              :value="item.shift_id"
              @input="updateShift(index, parseInt(($event.target as HTMLSelectElement).value))"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
              <option
                v-for="shift in availableShifts"
                :key="shift.id"
                :value="shift.id"
              >
                {{ shift.name }} ({{ shift.start_time.slice(0, 5) }} - {{ shift.end_time.slice(0, 5) }})
              </option>
            </select>
          </div>

          <div>
            <Label class="text-xs text-muted-foreground mb-1">Duration (days)</Label>
            <Input
              type="number"
              :model-value="item.duration_days"
              @update:model-value="updateDuration(index, parseInt($event as string))"
              min="1"
              class="h-9"
            />
          </div>
        </div>

        <!-- Shift Preview -->
        <div
          v-if="item.shift"
          class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-md text-white text-xs font-medium"
          :style="{ backgroundColor: item.shift.color_code || '#3b82f6' }"
        >
          <Clock class="h-3 w-3" />
          <span>{{ item.shift.start_time.slice(0, 5) }} - {{ item.shift.end_time.slice(0, 5) }}</span>
        </div>

        <!-- Remove Button -->
        <Button
          type="button"
          size="icon"
          variant="ghost"
          class="text-destructive hover:text-destructive hover:bg-destructive/10"
          @click="removeSequenceItem(index)"
        >
          <Trash2 class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <!-- Sequence Summary -->
    <div v-if="sequence.length > 0" class="p-4 rounded-lg bg-muted/30 border">
      <div class="text-xs font-medium mb-2">Rotation Summary</div>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="(item, index) in sequence"
          :key="index"
          class="flex items-center gap-1.5 px-2 py-1 rounded text-white text-[10px] font-medium"
          :style="{ backgroundColor: item.shift?.color_code || '#3b82f6' }"
        >
          <span>{{ index + 1 }}.</span>
          <span>{{ item.shift?.name }}</span>
          <span class="opacity-75">({{ item.duration_days }}d)</span>
        </div>
      </div>
    </div>
  </div>
</template>
