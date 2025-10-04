<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Edit,
  Wifi,
  WifiOff,
  Monitor,
  MapPin,
  Globe,
  HardDrive,
  Activity,
  Calendar,
  Network
} from 'lucide-vue-next'
import devices from '@/routes/devices'

interface Device {
  id: number
  device_id: string
  name: string
  location: string | null
  device_type: string
  ip_address: string | null
  mac_address: string | null
  firmware_version: string | null
  capacity: number | null
  current_count: number
  is_entry_device: boolean
  is_exit_device: boolean
  timezone: string
  settings: Record<string, any> | null
  is_active: boolean
  status: 'online' | 'offline' | 'inactive'
  last_heartbeat_at: string | null
  last_sync_at: string | null
  created_at: string
}

interface Props {
  device: Device
}

const props = defineProps<Props>()

const formatDate = (dateString: string | null) => {
  if (!dateString) return 'Never'
  return new Date(dateString).toLocaleString()
}

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Devices', href: '/devices' },
  { label: props.device.name, href: '#' },
]
</script>

<template>
  <Head :title="device.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            {{ device.name }}
          </h1>
          <p class="text-muted-foreground mt-2 font-mono text-sm">
            {{ device.device_id }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Badge v-if="device.status === 'online'" variant="default" class="bg-green-600">
            <Wifi class="mr-1 h-3 w-3" />
            Online
          </Badge>
          <Badge v-else-if="device.status === 'offline'" variant="destructive">
            <WifiOff class="mr-1 h-3 w-3" />
            Offline
          </Badge>
          <Badge v-else variant="secondary">
            <WifiOff class="mr-1 h-3 w-3" />
            Inactive
          </Badge>
          <Button as-child>
            <Link :href="devices.edit(device.id).url">
              <Edit class="mr-2 h-4 w-4" />
              Edit Settings
            </Link>
          </Button>
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <!-- Device Information -->
        <Card class="md:col-span-2">
          <CardHeader>
            <CardTitle>Device Information</CardTitle>
            <CardDescription>Hardware and configuration details</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Monitor class="h-4 w-4" />
                  <span>Device Type</span>
                </div>
                <p class="font-medium">{{ device.device_type }}</p>
              </div>

              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <MapPin class="h-4 w-4" />
                  <span>Location</span>
                </div>
                <p class="font-medium">{{ device.location || 'Not set' }}</p>
              </div>

              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Network class="h-4 w-4" />
                  <span>IP Address</span>
                </div>
                <p class="font-medium font-mono text-sm">{{ device.ip_address || 'Not set' }}</p>
              </div>

              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Network class="h-4 w-4" />
                  <span>MAC Address</span>
                </div>
                <p class="font-medium font-mono text-sm">{{ device.mac_address || 'Not set' }}</p>
              </div>

              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <HardDrive class="h-4 w-4" />
                  <span>Firmware Version</span>
                </div>
                <p class="font-medium">{{ device.firmware_version || 'Unknown' }}</p>
              </div>

              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Globe class="h-4 w-4" />
                  <span>Timezone</span>
                </div>
                <p class="font-medium">{{ device.timezone }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Status -->
        <Card>
          <CardHeader>
            <CardTitle>Status</CardTitle>
            <CardDescription>Device health and activity</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Activity class="h-4 w-4" />
                <span>Status</span>
              </div>
              <p class="font-medium capitalize">{{ device.status }}</p>
            </div>

            <div class="space-y-2">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Calendar class="h-4 w-4" />
                <span>Last Heartbeat</span>
              </div>
              <p class="text-sm">{{ formatDate(device.last_heartbeat_at) }}</p>
            </div>

            <div class="space-y-2">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Calendar class="h-4 w-4" />
                <span>Last Sync</span>
              </div>
              <p class="text-sm">{{ formatDate(device.last_sync_at) }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Capacity -->
        <Card>
          <CardHeader>
            <CardTitle>Capacity</CardTitle>
            <CardDescription>Face template storage</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div>
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium">Templates Used</span>
                  <span class="text-sm text-muted-foreground">
                    {{ device.current_count }} / {{ device.capacity || 'N/A' }}
                  </span>
                </div>
                <div v-if="device.capacity" class="w-full bg-secondary rounded-full h-2">
                  <div
                    class="bg-primary h-2 rounded-full transition-all"
                    :style="{ width: `${Math.min((device.current_count / device.capacity) * 100, 100)}%` }"
                  ></div>
                </div>
              </div>
              <p class="text-sm text-muted-foreground">
                {{ device.capacity ? Math.max(0, device.capacity - device.current_count).toLocaleString() : 'N/A' }} slots available
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Configuration -->
        <Card class="md:col-span-2">
          <CardHeader>
            <CardTitle>Configuration</CardTitle>
            <CardDescription>Operational settings</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid gap-4 md:grid-cols-3">
              <div class="flex items-center justify-between p-4 rounded-lg border">
                <span class="font-medium">Entry Device</span>
                <Badge :variant="device.is_entry_device ? 'default' : 'secondary'">
                  {{ device.is_entry_device ? 'Enabled' : 'Disabled' }}
                </Badge>
              </div>

              <div class="flex items-center justify-between p-4 rounded-lg border">
                <span class="font-medium">Exit Device</span>
                <Badge :variant="device.is_exit_device ? 'default' : 'secondary'">
                  {{ device.is_exit_device ? 'Enabled' : 'Disabled' }}
                </Badge>
              </div>

              <div class="flex items-center justify-between p-4 rounded-lg border">
                <span class="font-medium">Device Active</span>
                <Badge :variant="device.is_active ? 'default' : 'secondary'">
                  {{ device.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
