<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue'
import {
  Building2,
  Database,
  Settings,
  Calendar,
  Users,
  Smartphone,
  Globe,
  Server,
  CheckCircle,
  XCircle,
  Key
} from 'lucide-vue-next'
import { ref, computed } from 'vue'
import superAdmin from '@/routes/super-admin'

const { tenants } = superAdmin
const page = usePage()

const adminCredentials = computed(() => page.props.admin_credentials as { email: string; password: string } | undefined)

interface Tenant {
  id: string
  company_name: string
  subdomain: string
  domain: string | null
  admin_email: string | null
  database_name: string
  database_host: string
  subscription_plan: string
  max_employees: number
  max_devices: number
  is_active: boolean
  created_at: string
  updated_at: string
}

interface Props {
  tenant: Tenant
}

const props = defineProps<Props>()

const provisioning = ref(false)
const sendingCredentials = ref(false)

const provisionTenant = () => {
  if (confirm('Are you sure you want to provision this tenant\'s database?')) {
    provisioning.value = true
    router.post(
      tenants.provision(props.tenant.id).url,
      {},
      {
        onFinish: () => {
          provisioning.value = false
        }
      }
    )
  }
}

const sendCredentials = () => {
  if (confirm('Send admin credentials to ' + props.tenant.admin_email + '?')) {
    sendingCredentials.value = true
    router.post(
      tenants.sendCredentials(props.tenant.id).url,
      {},
      {
        onFinish: () => {
          sendingCredentials.value = false
        }
      }
    )
  }
}

const deleteTenant = () => {
  if (confirm('Are you sure you want to deactivate this tenant?')) {
    router.delete(tenants.destroy(props.tenant.id).url)
  }
}

const subscriptionColors = {
  starter: 'bg-gray-100 text-gray-800',
  professional: 'bg-blue-100 text-blue-800',
  enterprise: 'bg-purple-100 text-purple-800',
}
</script>

<template>
  <SuperAdminLayout :breadcrumbs="[
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tenants', href: '/tenants' },
    { title: tenant.company_name, href: `/tenants/${tenant.id}` }
  ]">
    <div class="space-y-6 p-6">
      <!-- Admin Credentials Alert -->
      <Alert v-if="adminCredentials" variant="default" class="border-green-500 bg-green-50 dark:bg-green-950">
        <Key class="h-4 w-4" />
        <AlertTitle>Tenant Provisioned Successfully</AlertTitle>
        <AlertDescription>
          <p class="mb-2">The tenant database has been created and the default admin user has been set up.</p>
          <div class="mt-4 space-y-2 bg-white dark:bg-gray-900 p-4 rounded border">
            <div class="flex items-center justify-between">
              <span class="font-semibold">Email:</span>
              <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{{ adminCredentials.email }}</code>
            </div>
            <div class="flex items-center justify-between">
              <span class="font-semibold">Password:</span>
              <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{{ adminCredentials.password }}</code>
            </div>
          </div>
          <div class="mt-4 space-y-2">
            <p class="text-sm text-green-600 dark:text-green-400">
              ✓ An email with login credentials has been sent to <strong>{{ adminCredentials.email }}</strong>
            </p>
            <p class="text-sm text-amber-600 dark:text-amber-400">
              ⚠️ Make sure to save these credentials! The password will not be shown again.
            </p>
          </div>
        </AlertDescription>
      </Alert>

      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold flex items-center gap-2">
            <Building2 class="h-6 w-6" />
            {{ tenant.company_name }}
          </h1>
          <p class="text-sm text-muted-foreground mt-1">
            {{ tenant.subdomain }}.mtan.test
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            variant="outline"
            @click="provisionTenant"
            :disabled="provisioning"
          >
            <Database class="mr-2 h-4 w-4" />
            {{ provisioning ? 'Provisioning...' : 'Provision Database' }}
          </Button>
          <Link :href="tenants.edit(tenant.id).url">
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
              <CheckCircle class="h-4 w-4 text-muted-foreground" />
              Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Badge
              :variant="tenant.is_active ? 'default' : 'destructive'"
              class="text-base"
            >
              {{ tenant.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium flex items-center gap-2">
              <Badge
                :class="subscriptionColors[tenant.subscription_plan as keyof typeof subscriptionColors]"
              >
                {{ tenant.subscription_plan }}
              </Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">Subscription Plan</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium flex items-center gap-2">
              <Calendar class="h-4 w-4 text-muted-foreground" />
              Created
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-base">
              {{ new Date(tenant.created_at).toLocaleDateString() }}
            </p>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Limits & Usage</CardTitle>
            <CardDescription>Resource allocation for this tenant</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Users class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Max Employees</span>
              </div>
              <span class="text-base font-semibold">{{ tenant.max_employees }}</span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Smartphone class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm">Max Devices</span>
              </div>
              <span class="text-base font-semibold">{{ tenant.max_devices }}</span>
            </div>
            <div v-if="tenant.admin_email" class="pt-2 border-t space-y-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Key class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm">Admin Email</span>
                </div>
                <span class="text-sm font-mono">{{ tenant.admin_email }}</span>
              </div>
              <Button
                variant="outline"
                size="sm"
                @click="sendCredentials"
                :disabled="sendingCredentials"
                class="w-full"
              >
                <Key class="mr-2 h-3 w-3" />
                {{ sendingCredentials ? 'Sending...' : 'Send Credentials Email' }}
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Database Configuration</CardTitle>
            <CardDescription>Database connection details</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Server class="h-4 w-4" />
                <span>Database Name</span>
              </div>
              <p class="font-mono text-xs bg-muted p-2 rounded">
                {{ tenant.database_name }}
              </p>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Globe class="h-4 w-4" />
                <span>Database Host</span>
              </div>
              <p class="font-mono text-xs bg-muted p-2 rounded">
                {{ tenant.database_host }}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Domain Configuration</CardTitle>
          <CardDescription>URLs where this tenant can be accessed</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
            <div>
              <p class="text-sm font-medium">Subdomain</p>
              <p class="text-sm text-muted-foreground">
                {{ tenant.subdomain }}.mtan.test
              </p>
            </div>
            <Badge variant="secondary">Primary</Badge>
          </div>
          <div
            v-if="tenant.domain"
            class="flex items-center justify-between p-3 bg-muted rounded-lg"
          >
            <div>
              <p class="text-sm font-medium">Custom Domain</p>
              <p class="text-sm text-muted-foreground">{{ tenant.domain }}</p>
            </div>
            <Badge variant="outline">Custom</Badge>
          </div>
          <div v-else class="flex items-center gap-2 text-sm text-muted-foreground">
            <XCircle class="h-4 w-4" />
            <span>No custom domain configured</span>
          </div>
        </CardContent>
      </Card>

      <div class="flex gap-2 justify-end">
        <Link :href="tenants.index().url">
          <Button variant="outline">Back to Tenants</Button>
        </Link>
        <Button variant="destructive" @click="deleteTenant">
          Deactivate Tenant
        </Button>
      </div>
    </div>
  </SuperAdminLayout>
</template>
