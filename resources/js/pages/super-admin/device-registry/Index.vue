<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue'
import { Smartphone, Plus, Settings, Eye, Wifi, WifiOff } from 'lucide-vue-next'
import { ref } from 'vue'
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
  is_active: boolean
  last_seen_at: string | null
  tenant: Tenant
}

interface Props {
  devices: {
    data: Device[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  tenants: Tenant[]
  filters: {
    tenant_id?: string
    is_active?: boolean
    search?: string
  }
}

const props = defineProps<Props>()

const search = ref(props.filters.search || '')
const selectedTenant = ref(props.filters.tenant_id || '')

const applyFilters = () => {
  router.get(superAdmin.deviceRegistry.index().url, {
    search: search.value,
    tenant_id: selectedTenant.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

const deleteDevice = (id: number) => {
  if (confirm('Are you sure you want to delete this device?')) {
    router.delete(superAdmin.deviceRegistry.destroy(id).url)
  }
}
</script>

<template>
  <SuperAdminLayout>
    <div class="space-y-6 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Device Registry</h1>
          <p class="text-sm text-muted-foreground">Manage device registrations across all tenants</p>
        </div>
        <Link :href="superAdmin.deviceRegistry.create().url">
          <Button>
            <Plus class="mr-2 h-4 w-4" />
            Register Device
          </Button>
        </Link>
      </div>

      <div class="flex gap-4">
        <div class="flex-1">
          <Input
            v-model="search"
            placeholder="Search by device ID, name, or location..."
            @keyup.enter="applyFilters"
          />
        </div>
        <select
          v-model="selectedTenant"
          @change="applyFilters"
          class="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          <option value="">All Tenants</option>
          <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
            {{ tenant.company_name }}
          </option>
        </select>
        <Button @click="applyFilters">Filter</Button>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card v-for="device in devices.data" :key="device.id">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <CardTitle class="flex items-center gap-2">
                  <Smartphone class="h-5 w-5" />
                  {{ device.device_name }}
                </CardTitle>
                <CardDescription>
                  {{ device.device_id }}
                </CardDescription>
              </div>
              <Badge :variant="device.is_active ? 'default' : 'destructive'" class="flex items-center gap-1">
                <component :is="device.is_active ? Wifi : WifiOff" class="h-3 w-3" />
                {{ device.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Tenant:</span>
                <span class="font-medium">{{ device.tenant.company_name }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Type:</span>
                <span class="font-medium capitalize">{{ device.device_type.replace('_', ' ') }}</span>
              </div>
              <div v-if="device.location" class="flex items-center justify-between">
                <span class="text-muted-foreground">Location:</span>
                <span class="font-medium">{{ device.location }}</span>
              </div>
              <div v-if="device.ip_address" class="flex items-center justify-between">
                <span class="text-muted-foreground">IP Address:</span>
                <span class="font-mono text-xs">{{ device.ip_address }}</span>
              </div>
            </div>

            <div class="flex gap-2">
              <Link :href="superAdmin.deviceRegistry.show(device.id).url" class="flex-1">
                <Button variant="outline" size="sm" class="w-full">
                  <Eye class="mr-2 h-4 w-4" />
                  View
                </Button>
              </Link>
              <Link :href="superAdmin.deviceRegistry.edit(device.id).url" class="flex-1">
                <Button variant="outline" size="sm" class="w-full">
                  <Settings class="mr-2 h-4 w-4" />
                  Edit
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-if="devices.data.length === 0" class="text-center py-12">
        <Smartphone class="mx-auto h-12 w-12 text-muted-foreground" />
        <h3 class="mt-4 text-lg font-semibold">No devices found</h3>
        <p class="text-sm text-muted-foreground">Get started by registering a new device.</p>
        <Link :href="superAdmin.deviceRegistry.create().url" class="mt-4 inline-block">
          <Button>
            <Plus class="mr-2 h-4 w-4" />
            Register Device
          </Button>
        </Link>
      </div>
    </div>
  </SuperAdminLayout>
</template>
