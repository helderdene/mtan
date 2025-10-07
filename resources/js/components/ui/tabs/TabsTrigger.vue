<script setup lang="ts">
import { inject, computed } from 'vue'

interface Props {
  value: string
  disabled?: boolean
}

const props = defineProps<Props>()

const tabs = inject<{
  activeTab: { value: string }
  setActiveTab: (value: string) => void
}>('tabs')

const isActive = computed(() => tabs?.activeTab.value === props.value)

const handleClick = () => {
  if (!props.disabled && tabs) {
    tabs.setActiveTab(props.value)
  }
}
</script>

<template>
  <button
    type="button"
    role="tab"
    :aria-selected="isActive"
    :disabled="disabled"
    :class="[
      'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
      isActive
        ? 'bg-background text-foreground shadow'
        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
    ]"
    @click="handleClick"
  >
    <slot />
  </button>
</template>
