<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Save, X, Smartphone, Network, Settings } from 'lucide-vue-next'
import devices from '@/routes/devices'

interface Device {
  id: number
  device_id: string
  name: string
  location: string
  ip_address: string | null
  capacity: number
  is_active: boolean
}

interface Props {
  device?: Device
}

const props = defineProps<Props>()

const isEdit = computed(() => !!props.device)

const form = useForm({
  device_id: props.device?.device_id || '',
  name: props.device?.name || '',
  location: props.device?.location || '',
  ip_address: props.device?.ip_address || '',
  capacity: props.device?.capacity || 3000,
  is_active: props.device?.is_active ?? true,
})

const submit = () => {
  if (isEdit.value) {
    form.put(devices.update(props.device!.id).url)
  } else {
    form.post(devices.store().url)
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit Device' : 'Create Device'" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          href="/dashboard"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <Link
          href="/devices"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Devices
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ isEdit ? 'Edit' : 'Create' }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            {{ isEdit ? 'Edit Device' : 'Add New Device' }}
          </h1>
          <p class="text-muted-foreground mt-2">
            {{ isEdit ? 'Update device information' : 'Register a new biometric attendance device' }}
          </p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="max-w-4xl space-y-6">
        <div class="rounded-xl border bg-card p-8 shadow-sm space-y-8">
          <!-- Device Information -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-emerald-500 to-green-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-emerald-500 to-green-500">
                <Smartphone class="h-4 w-4 text-white" />
              </div>
              Device Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <Label for="device_id" class="text-sm font-medium">
                  Device ID <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="device_id"
                  v-model="form.device_id"
                  placeholder="e.g. DEVICE001"
                  :disabled="isEdit"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.device_id" class="text-sm text-destructive">
                  {{ form.errors.device_id }}
                </p>
                <p v-if="!isEdit" class="text-xs text-muted-foreground">
                  Unique identifier for the device (cannot be changed later)
                </p>
              </div>

              <div class="space-y-2">
                <Label for="name" class="text-sm font-medium">
                  Device Name <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="e.g. Main Entrance"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                  {{ form.errors.name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="location" class="text-sm font-medium">
                  Location <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="location"
                  v-model="form.location"
                  placeholder="e.g. Building A - 1st Floor"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.location" class="text-sm text-destructive">
                  {{ form.errors.location }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="ip_address" class="text-sm font-medium">IP Address</Label>
                <Input
                  id="ip_address"
                  v-model="form.ip_address"
                  placeholder="e.g. 192.168.1.100"
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.ip_address" class="text-sm text-destructive">
                  {{ form.errors.ip_address }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="capacity" class="text-sm font-medium">
                  Face Capacity <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="capacity"
                  v-model="form.capacity"
                  type="number"
                  min="1"
                  max="10000"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.capacity" class="text-sm text-destructive">
                  {{ form.errors.capacity }}
                </p>
                <p class="text-xs text-muted-foreground">
                  Maximum number of faces this device can store
                </p>
              </div>

              <div class="space-y-2">
                <Label for="is_active" class="text-sm font-medium">Device Status</Label>
                <div class="flex items-center space-x-2 h-10">
                  <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input"
                  />
                  <Label for="is_active" class="cursor-pointer">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                  </Label>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ form.is_active ? 'Device is enabled and processing attendance' : 'Device is disabled' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="rounded-xl border bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:to-gray-950 p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
              <span class="text-destructive">*</span> Required fields
            </p>
            <div class="flex items-center gap-3">
              <Link :href="devices.index().url">
                <Button type="button" variant="outline" class="gap-2">
                  <X class="h-4 w-4" />
                  Cancel
                </Button>
              </Link>
              <Button
                type="submit"
                :disabled="form.processing"
                class="gap-2 bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 shadow-lg shadow-emerald-500/30"
              >
                <Save class="h-4 w-4" />
                {{ isEdit ? 'Update Device' : 'Create Device' }}
              </Button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
