<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Edit, Smartphone, Wifi, WifiOff, Activity, MonitorIcon } from 'lucide-vue-next'
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
  is_active: boolean
  status: 'online' | 'offline' | 'inactive'
  last_heartbeat_at: string | null
  last_sync_at: string | null
  attendance_records_count: number
}

interface Props {
  devices: Device[]
}

const props = defineProps<Props>()

const formatDate = (dateString: string | null) => {
  if (!dateString) return 'Never'
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <AppLayout>
    <Head title="Devices" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          href="/dashboard"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Devices</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Biometric Devices
          </h1>
          <p class="text-muted-foreground mt-2">
            View and manage attendance tracking devices. Contact your administrator to add new devices.
          </p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Devices Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Smartphone class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-emerald-100">Total Devices</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.devices.length }}</p>
              <p class="text-xs text-emerald-100 mt-2">
                Registered devices
              </p>
            </div>
          </div>
        </div>

        <!-- Online Devices Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-green-500 to-green-600 p-6 shadow-lg shadow-green-500/20 transition-all hover:shadow-xl hover:shadow-green-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Wifi class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-green-100">Online Devices</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.devices.filter(d => d.status === 'online').length }}
              </p>
              <p class="text-xs text-green-100 mt-2">
                Currently online
              </p>
            </div>
          </div>
        </div>

        <!-- Active Devices Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg shadow-blue-500/20 transition-all hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Activity class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-blue-100">Active Devices</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.devices.filter(d => d.is_active).length }}
              </p>
              <p class="text-xs text-blue-100 mt-2">
                Enabled devices
              </p>
            </div>
          </div>
        </div>

        <!-- Total Capacity Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-6 shadow-lg shadow-purple-500/20 transition-all hover:shadow-xl hover:shadow-purple-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Smartphone class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-purple-100">Total Capacity</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.devices.reduce((sum, d) => sum + (d.capacity || 0), 0).toLocaleString() }}
              </p>
              <p class="text-xs text-purple-100 mt-2">
                Face templates
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Device ID</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Location</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Capacity</TableHead>
              <TableHead>Last Heartbeat</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="device in props.devices" :key="device.id">
              <TableCell class="font-medium font-mono text-xs">
                {{ device.device_id }}
              </TableCell>
              <TableCell>
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                    <Smartphone class="h-5 w-5 text-white" />
                  </div>
                  <div class="font-medium">{{ device.name }}</div>
                </div>
              </TableCell>
              <TableCell>
                <span class="text-sm">{{ device.location || '-' }}</span>
              </TableCell>
              <TableCell>
                <span class="text-sm">{{ device.device_type }}</span>
              </TableCell>
              <TableCell>
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
              </TableCell>
              <TableCell>
                <span class="text-sm">{{ device.current_count }} / {{ device.capacity || 'N/A' }}</span>
              </TableCell>
              <TableCell>
                <span class="text-sm text-muted-foreground">{{ formatDate(device.last_heartbeat_at) }}</span>
              </TableCell>
              <TableCell class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="devices.show(device.id).url">
                    <Button variant="ghost" size="sm">
                      <Activity class="h-4 w-4" />
                    </Button>
                  </Link>
                  <Link :href="devices.edit(device.id).url">
                    <Button variant="ghost" size="sm">
                      <Edit class="h-4 w-4" />
                    </Button>
                  </Link>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="props.devices.length === 0">
              <TableCell colspan="8" class="text-center py-12">
                <div class="flex flex-col items-center gap-2">
                  <Smartphone class="h-12 w-12 text-muted-foreground" />
                  <p class="text-muted-foreground">No devices assigned yet</p>
                  <p class="text-sm text-muted-foreground">Contact your administrator to add devices</p>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template>
