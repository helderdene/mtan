<script setup lang="ts">
import { provide, ref, watch } from 'vue'

interface Props {
  modelValue?: string
  defaultValue?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const activeTab = ref(props.modelValue || props.defaultValue || '')

watch(() => props.modelValue, (value) => {
  if (value !== undefined) {
    activeTab.value = value
  }
})

const setActiveTab = (value: string) => {
  activeTab.value = value
  emit('update:modelValue', value)
}

provide('tabs', {
  activeTab,
  setActiveTab
})
</script>

<template>
  <div>
    <slot />
  </div>
</template>
