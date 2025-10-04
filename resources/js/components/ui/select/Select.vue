<script setup lang="ts">
import { provide, ref, watch } from 'vue'

const props = defineProps<{
  modelValue?: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: any]
}>()

const value = ref(props.modelValue)

watch(() => props.modelValue, (newValue) => {
  value.value = newValue
})

const updateValue = (newValue: any) => {
  value.value = newValue
  emit('update:modelValue', newValue)
}

provide('selectValue', value)
provide('updateSelectValue', updateValue)
</script>

<template>
  <div class="relative">
    <slot />
  </div>
</template>
