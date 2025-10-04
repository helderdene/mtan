<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue'
import {
  Smartphone,
  Settings,
  Calendar,
  Building2,
  MapPin,
  Network,
  Cpu,
  Wifi,
  WifiOff,
  Clock
} from 'lucide-vue-next'
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
  last_seen_at: string | null
  registered_at: string
  created_at: string
  tenant: Tenant
}

interface Props {
  device: Device
}

const props = defineProps<Props>()

const deleteDevice = () => {
  if (confirm('Are you sure you want to delete this device?')) {
    router.delete(superAdmin.deviceRegistry.destroy(props.device.id).url)
  }
}
</script>

<template>
  <SuperAdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold flex items-center gap-2">
            <Smartphone class="h-6 w-6" />
            {{ device.device_name }}
          </h1>
          <p class="text-sm text-muted-foreground mt-1">
            {{ device.device_id }}
          </p>
        </div>
        <div class="flex gap-2">
          <Link :href="superAdmin.deviceRegistry.edit(device.id).url">
            <Button>
              <Settings class="mr-2 h-4 w-4" />
              Edit
            </Button>
          </Link>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium flex items-center gap-2">
              <component :is="device.is_active ? Wifi : WifiOff" class="h-4 w-4 text-muted-foreground" />
              Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Badge
              :variant="device.is_active ? 'default' : 'destructive'"
              class="text-base"
            >
              {{ device.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium flex items-center gap-2">
              <Building2 class="h-4 w-4 text-muted-foreground" />
              Tenant
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-base font-semibold">{{ device.tenant.company_name }}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium flex items-center gap-2">
              <Calendar class="h-4 w-4 text-muted-foreground" />
              Registered
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-base">
              {{ new Date(device.registered_at).toLocaleDateString() }}
            </p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Device Details</CardTitle>
            <CardDescription>Basic device information</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Smartphone class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Device Type</span>
              </div>
              <span class="text-base font-semibold capitalize">
                {{ device.device_type.replace('_', ' ') }}
              </span>
            </div>
            <div v-if="device.location" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <MapPin class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Location</span>
              </div>
              <span class="text-base font-semibold">{{ device.location }}</span>
            </div>
            <div v-if="device.firmware_version" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Cpu class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Firmware</span>
              </div>
              <span class="text-base font-mono text-xs">{{ device.firmware_version }}</span>
            </div>
            <div v-if="device.last_seen_at" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Last Seen</span>
              </div>
              <span class="text-sm">{{ new Date(device.last_seen_at).toLocaleString() }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Network Configuration</CardTitle>
            <CardDescription>Network connection details</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="device.ip_address" class="space-y-1">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Network class="h-4 w-4" />
                <span>IP Address</span>
              </div>
              <p class="font-mono text-xs bg-muted p-2 rounded">
                {{ device.ip_address }}
              </p>
            </div>
            <div v-if="device.mac_address" class="space-y-1">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Network class="h-4 w-4" />
                <span>MAC Address</span>
              </div>
              <p class="font-mono text-xs bg-muted p-2 rounded">
                {{ device.mac_address }}
              </p>
            </div>
            <div v-if="!device.ip_address && !device.mac_address" class="text-sm text-muted-foreground">
              No network configuration available
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="flex gap-2 justify-end">
        <Link :href="superAdmin.deviceRegistry.index().url">
          <Button variant="outline">Back to Device Registry</Button>
        </Link>
        <Button variant="destructive" @click="deleteDevice">
          Delete Device
        </Button>
      </div>
    </div>
  </SuperAdminLayout>
</template>
