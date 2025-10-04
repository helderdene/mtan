<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Save, X, Settings as SettingsIcon, MapPin, Globe } from 'lucide-vue-next'
import devices from '@/routes/devices'

interface Device {
  id: number
  device_id: string
  name: string
  location: string | null
  device_type: string
  ip_address: string | null
  timezone: string
  is_entry_device: boolean
  is_exit_device: boolean
  is_active: boolean
}

interface Props {
  device: Device
}

const props = defineProps<Props>()

const form = useForm({
  name: props.device.name,
  location: props.device.location || '',
  ip_address: props.device.ip_address || '',
  timezone: props.device.timezone,
  is_entry_device: props.device.is_entry_device,
  is_exit_device: props.device.is_exit_device,
  is_active: props.device.is_active,
})

const submit = () => {
  form.put(devices.update(props.device.id).url)
}

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Devices', href: '/devices' },
  { label: 'Edit Settings', href: '#' },
]
</script>

<template>
  <Head title="Edit Device Settings" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
          Edit Device Settings
        </h1>
        <p class="text-muted-foreground mt-2">
          Update device configuration and operational settings
        </p>
      </div>

      <!-- Device Info -->
      <Card>
        <CardHeader>
          <CardTitle>Device Information</CardTitle>
          <CardDescription>
            Device ID: <span class="font-mono font-medium">{{ device.device_id }}</span> | Type: {{ device.device_type }}
          </CardDescription>
        </CardHeader>
      </Card>

      <!-- Form -->
      <form @submit.prevent="submit">
        <div class="space-y-6">
          <!-- Basic Settings -->
          <Card>
            <CardHeader>
              <div class="flex items-center gap-2">
                <SettingsIcon class="h-5 w-5" />
                <CardTitle>Basic Settings</CardTitle>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="name">Device Name *</Label>
                  <Input
                    id="name"
                    v-model="form.name"
                    :class="{ 'border-destructive': form.errors.name }"
                    required
                  />
                  <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="location">
                    <MapPin class="inline h-4 w-4 mr-1" />
                    Location
                  </Label>
                  <Input
                    id="location"
                    v-model="form.location"
                    :class="{ 'border-destructive': form.errors.location }"
                    placeholder="e.g., Main Entrance"
                  />
                  <p v-if="form.errors.location" class="text-sm text-destructive">
                    {{ form.errors.location }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="ip_address">IP Address</Label>
                  <Input
                    id="ip_address"
                    v-model="form.ip_address"
                    :class="{ 'border-destructive': form.errors.ip_address }"
                    placeholder="192.168.1.100"
                  />
                  <p v-if="form.errors.ip_address" class="text-sm text-destructive">
                    {{ form.errors.ip_address }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="timezone">
                    <Globe class="inline h-4 w-4 mr-1" />
                    Timezone *
                  </Label>
                  <Input
                    id="timezone"
                    v-model="form.timezone"
                    :class="{ 'border-destructive': form.errors.timezone }"
                    placeholder="UTC"
                    required
                  />
                  <p v-if="form.errors.timezone" class="text-sm text-destructive">
                    {{ form.errors.timezone }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Operational Settings -->
          <Card>
            <CardHeader>
              <CardTitle>Operational Settings</CardTitle>
              <CardDescription>Configure device behavior and access control</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label for="is_entry_device">Entry Device</Label>
                  <p class="text-sm text-muted-foreground">
                    Allow check-in at this device
                  </p>
                </div>
                <Checkbox
                  id="is_entry_device"
                  :checked="form.is_entry_device"
                  @update:checked="form.is_entry_device = $event"
                />
              </div>

              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label for="is_exit_device">Exit Device</Label>
                  <p class="text-sm text-muted-foreground">
                    Allow check-out at this device
                  </p>
                </div>
                <Checkbox
                  id="is_exit_device"
                  :checked="form.is_exit_device"
                  @update:checked="form.is_exit_device = $event"
                />
              </div>

              <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                  <Label for="is_active">Device Active</Label>
                  <p class="text-sm text-muted-foreground">
                    Enable or disable this device
                  </p>
                </div>
                <Checkbox
                  id="is_active"
                  :checked="form.is_active"
                  @update:checked="form.is_active = $event"
                />
              </div>
            </CardContent>
          </Card>

          <!-- Actions -->
          <div class="flex items-center gap-4">
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              {{ form.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
            <Button as-child variant="outline" type="button">
              <Link :href="devices.index().url">
                <X class="mr-2 h-4 w-4" />
                Cancel
              </Link>
            </Button>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
