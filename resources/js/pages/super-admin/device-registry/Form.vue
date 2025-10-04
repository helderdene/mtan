<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue'
import { Smartphone } from 'lucide-vue-next'
import superAdmin from '@/routes/super-admin'

interface Tenant {
  id: string
  company_name: string
}

interface Device {
  id: number
  tenant_id: string
  device_id: string
  device_name: string
  device_type: string
  location: string | null
  ip_address: string | null
  mac_address: string | null
  firmware_version: string | null
  is_active: boolean
}

interface Props {
  device?: Device
  tenants: Tenant[]
}

const props = defineProps<Props>()

const form = useForm({
  tenant_id: props.device?.tenant_id || '',
  device_id: props.device?.device_id || '',
  device_name: props.device?.device_name || '',
  device_type: props.device?.device_type || 'facial_recognition',
  location: props.device?.location || '',
  ip_address: props.device?.ip_address || '',
  mac_address: props.device?.mac_address || '',
  firmware_version: props.device?.firmware_version || '',
  is_active: props.device?.is_active ?? true,
})

const submit = () => {
  if (props.device) {
    form.put(superAdmin.deviceRegistry.update(props.device.id).url)
  } else {
    form.post(superAdmin.deviceRegistry.store().url)
  }
}

const deviceTypes = [
  { value: 'facial_recognition', label: 'Facial Recognition' },
  { value: 'fingerprint', label: 'Fingerprint Scanner' },
  { value: 'card_reader', label: 'Card Reader' },
  { value: 'biometric_hybrid', label: 'Biometric Hybrid' },
]
</script>

<template>
  <SuperAdminLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-semibold">{{ device ? 'Edit' : 'Register' }} Device</h1>
        <p class="text-sm text-muted-foreground">
          {{ device ? 'Update device registration information' : 'Register a new device in the central registry' }}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Smartphone class="h-5 w-5" />
            Device Information
          </CardTitle>
          <CardDescription>
            Configure the device registration details.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <div v-if="!device" class="space-y-2">
              <Label for="tenant_id">Tenant *</Label>
              <select
                id="tenant_id"
                v-model="form.tenant_id"
                required
                :disabled="form.processing"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <option value="" disabled>Select a tenant</option>
                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                  {{ tenant.company_name }}
                </option>
              </select>
              <p v-if="form.errors.tenant_id" class="text-sm text-destructive">
                {{ form.errors.tenant_id }}
              </p>
              <p v-else class="text-xs text-muted-foreground">
                Tenant cannot be changed after registration
              </p>
            </div>

            <div class="space-y-2">
              <Label for="device_id">Device ID *</Label>
              <Input
                id="device_id"
                v-model="form.device_id"
                type="text"
                required
                :disabled="form.processing || !!device"
                placeholder="DEV001"
              />
              <p v-if="form.errors.device_id" class="text-sm text-destructive">
                {{ form.errors.device_id }}
              </p>
              <p v-else-if="device" class="text-xs text-muted-foreground">
                Device ID cannot be changed after registration
              </p>
              <p v-else class="text-xs text-muted-foreground">
                Unique identifier for the device (e.g., DEV001)
              </p>
            </div>

            <div class="space-y-2">
              <Label for="device_name">Device Name *</Label>
              <Input
                id="device_name"
                v-model="form.device_name"
                type="text"
                required
                :disabled="form.processing"
                placeholder="Main Entrance"
              />
              <p v-if="form.errors.device_name" class="text-sm text-destructive">
                {{ form.errors.device_name }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="device_type">Device Type *</Label>
              <select
                id="device_type"
                v-model="form.device_type"
                required
                :disabled="form.processing"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <option v-for="type in deviceTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
              <p v-if="form.errors.device_type" class="text-sm text-destructive">
                {{ form.errors.device_type }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="location">Location</Label>
              <Input
                id="location"
                v-model="form.location"
                type="text"
                :disabled="form.processing"
                placeholder="Building A - Main Entrance"
              />
              <p v-if="form.errors.location" class="text-sm text-destructive">
                {{ form.errors.location }}
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="ip_address">IP Address</Label>
                <Input
                  id="ip_address"
                  v-model="form.ip_address"
                  type="text"
                  :disabled="form.processing"
                  placeholder="192.168.1.100"
                />
                <p v-if="form.errors.ip_address" class="text-sm text-destructive">
                  {{ form.errors.ip_address }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="mac_address">MAC Address</Label>
                <Input
                  id="mac_address"
                  v-model="form.mac_address"
                  type="text"
                  :disabled="form.processing"
                  placeholder="AA:BB:CC:DD:EE:FF"
                />
                <p v-if="form.errors.mac_address" class="text-sm text-destructive">
                  {{ form.errors.mac_address }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="firmware_version">Firmware Version</Label>
              <Input
                id="firmware_version"
                v-model="form.firmware_version"
                type="text"
                :disabled="form.processing"
                placeholder="1.0.0"
              />
              <p v-if="form.errors.firmware_version" class="text-sm text-destructive">
                {{ form.errors.firmware_version }}
              </p>
            </div>

            <div class="flex items-center space-x-2">
              <Checkbox
                id="is_active"
                v-model:checked="form.is_active"
                :disabled="form.processing"
              />
              <div class="space-y-0.5">
                <Label for="is_active" class="cursor-pointer">Active Status</Label>
                <p class="text-xs text-muted-foreground">
                  Inactive devices will not process attendance events
                </p>
              </div>
            </div>

            <div class="flex gap-2 justify-end">
              <Button
                type="button"
                variant="outline"
                @click="$inertia.visit(superAdmin.deviceRegistry.index().url)"
                :disabled="form.processing"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : (device ? 'Update' : 'Register') }} Device
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
