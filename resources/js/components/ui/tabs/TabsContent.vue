<script setup lang="ts">
import { inject, computed } from 'vue'

interface Props {
  value: string
}

const props = defineProps<Props>()

const tabs = inject<{
  activeTab: { value: string }
}>('tabs')

const isActive = computed(() => tabs?.activeTab.value === props.value)
</script>

<template>
  <div
    v-if="isActive"
    role="tabpanel"
    :aria-hidden="!isActive"
  >
    <slot />
  </div>
</template>
