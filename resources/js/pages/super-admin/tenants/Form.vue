<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue'
import { Building2 } from 'lucide-vue-next'
import superAdmin from '@/routes/super-admin'

const { tenants } = superAdmin

interface Tenant {
  id: string
  company_name: string
  subdomain: string
  domain: string | null
  subscription_plan: string
  max_employees: number
  max_devices: number
  is_active: boolean
}

interface Props {
  tenant?: Tenant
}

const props = defineProps<Props>()

const form = useForm({
  company_name: props.tenant?.company_name || '',
  subdomain: props.tenant?.subdomain || '',
  domain: props.tenant?.domain || null,
  admin_email: '',
  subscription_plan: props.tenant?.subscription_plan || 'professional',
  max_employees: props.tenant?.max_employees || 100,
  max_devices: props.tenant?.max_devices || 10,
  is_active: props.tenant?.is_active ?? true,
})

const submit = () => {
  if (props.tenant) {
    form.put(tenants.update(props.tenant.id).url)
  } else {
    form.post(tenants.store().url)
  }
}

const subscriptionPlans = [
  { value: 'starter', label: 'Starter', employees: 50, devices: 5 },
  { value: 'professional', label: 'Professional', employees: 100, devices: 10 },
  { value: 'enterprise', label: 'Enterprise', employees: 500, devices: 50 },
]

const updateLimits = (plan: string) => {
  const selected = subscriptionPlans.find(p => p.value === plan)
  if (selected) {
    form.max_employees = selected.employees
    form.max_devices = selected.devices
  }
}
</script>

<template>
  <SuperAdminLayout :breadcrumbs="[
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tenants', href: '/tenants' },
    { title: tenant ? 'Edit Tenant' : 'Create Tenant', href: tenant ? `/tenants/${tenant.id}/edit` : '/tenants/create' }
  ]">
    <div class="max-w-2xl mx-auto space-y-6 p-6">
      <div>
        <h1 class="text-2xl font-semibold">{{ tenant ? 'Edit' : 'Create' }} Tenant</h1>
        <p class="text-sm text-muted-foreground">
          {{ tenant ? 'Update tenant information' : 'Add a new tenant organization' }}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            Tenant Information
          </CardTitle>
          <CardDescription>
            Configure the tenant's basic information and subscription details.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
              <Label for="company_name">Company Name *</Label>
              <Input
                id="company_name"
                v-model="form.company_name"
                type="text"
                required
                :disabled="form.processing"
              />
              <p v-if="form.errors.company_name" class="text-sm text-destructive">
                {{ form.errors.company_name }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="subdomain">Subdomain *</Label>
              <div class="flex items-center gap-2">
                <Input
                  id="subdomain"
                  v-model="form.subdomain"
                  type="text"
                  required
                  :disabled="form.processing || !!tenant"
                  placeholder="company"
                />
                <span class="text-sm text-muted-foreground whitespace-nowrap">
                  .mtan.test
                </span>
              </div>
              <p v-if="form.errors.subdomain" class="text-sm text-destructive">
                {{ form.errors.subdomain }}
              </p>
              <p v-else class="text-xs text-muted-foreground">
                Subdomain cannot be changed after creation
              </p>
            </div>

            <div class="space-y-2">
              <Label for="domain">Custom Domain (Optional)</Label>
              <Input
                id="domain"
                v-model="form.domain"
                type="text"
                :disabled="form.processing"
                placeholder="company.mtan.test"
              />
              <p v-if="form.errors.domain" class="text-sm text-destructive">
                {{ form.errors.domain }}
              </p>
            </div>

            <div v-if="!tenant" class="space-y-2">
              <Label for="admin_email">Admin Email *</Label>
              <Input
                id="admin_email"
                v-model="form.admin_email"
                type="email"
                required
                :disabled="form.processing"
                placeholder="admin@company.com"
              />
              <p v-if="form.errors.admin_email" class="text-sm text-destructive">
                {{ form.errors.admin_email }}
              </p>
              <p v-else class="text-xs text-muted-foreground">
                A temporary password will be generated for the admin user
              </p>
            </div>

            <div class="space-y-2">
              <Label for="subscription_plan">Subscription Plan *</Label>
              <select
                id="subscription_plan"
                v-model="form.subscription_plan"
                @change="updateLimits(form.subscription_plan)"
                required
                :disabled="form.processing"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <option value="" disabled>Select a plan</option>
                <option
                  v-for="plan in subscriptionPlans"
                  :key="plan.value"
                  :value="plan.value"
                >
                  {{ plan.label }} ({{ plan.employees }} employees, {{ plan.devices }} devices)
                </option>
              </select>
              <p v-if="form.errors.subscription_plan" class="text-sm text-destructive">
                {{ form.errors.subscription_plan }}
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="max_employees">Max Employees *</Label>
                <Input
                  id="max_employees"
                  v-model.number="form.max_employees"
                  type="number"
                  min="1"
                  required
                  :disabled="form.processing"
                />
                <p v-if="form.errors.max_employees" class="text-sm text-destructive">
                  {{ form.errors.max_employees }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="max_devices">Max Devices *</Label>
                <Input
                  id="max_devices"
                  v-model.number="form.max_devices"
                  type="number"
                  min="1"
                  required
                  :disabled="form.processing"
                />
                <p v-if="form.errors.max_devices" class="text-sm text-destructive">
                  {{ form.errors.max_devices }}
                </p>
              </div>
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
                  Inactive tenants cannot access the system
                </p>
              </div>
            </div>

            <div class="flex gap-2 justify-end">
              <Button
                type="button"
                variant="outline"
                @click="router.visit(tenants.index().url)"
                :disabled="form.processing"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : (tenant ? 'Update' : 'Create') }} Tenant
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
