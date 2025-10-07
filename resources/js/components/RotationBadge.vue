<script setup lang="ts">
import { computed } from 'vue'
import { Repeat } from 'lucide-vue-next'

interface Props {
  rotationName: string
  cycleType: 'daily' | 'weekly' | 'monthly'
  currentPosition?: number
  totalPositions?: number
  variant?: 'default' | 'compact'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default'
})

const cycleTypeLabel = computed(() => {
  const labels = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly'
  }
  return labels[props.cycleType]
})

const badgeColor = computed(() => {
  const colors = {
    daily: 'bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
    weekly: 'bg-purple-100 text-purple-700 dark:bg-purple-950/30 dark:text-purple-400 border-purple-200 dark:border-purple-800',
    monthly: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
  }
  return colors[props.cycleType]
})

const progressText = computed(() => {
  if (props.currentPosition !== undefined && props.totalPositions !== undefined) {
    return `${props.currentPosition}/${props.totalPositions}`
  }
  return null
})
</script>

<template>
  <div
    v-if="variant === 'default'"
    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium"
    :class="badgeColor"
  >
    <Repeat class="h-4 w-4" />
    <span class="font-semibold">{{ rotationName }}</span>
    <span class="text-xs opacity-75">{{ cycleTypeLabel }}</span>
    <span v-if="progressText" class="text-xs font-mono bg-white/50 dark:bg-black/20 px-2 py-0.5 rounded">
      {{ progressText }}
    </span>
  </div>

  <div
    v-else
    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md border text-xs font-medium"
    :class="badgeColor"
  >
    <Repeat class="h-3 w-3" />
    <span>{{ rotationName }}</span>
  </div>
</template>
