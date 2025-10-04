<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Plus, Search, Edit, Trash2, UserCircle, Eye } from 'lucide-vue-next'
import employees from '@/routes/employees'
import { dashboard } from '@/routes'

interface Employee {
  id: number
  custom_id: string
  first_name: string
  last_name: string
  full_name: string
  email: string
  phone: string | null
  avatar: string | null
  avatar_url: string | null
  department: {
    id: number
    name: string
  } | null
  is_active: boolean
  hired_at: string | null
  created_at: string
}

interface Props {
  employees: {
    data: Employee[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: {
    search?: string
    department_id?: number
    status?: string
  }
}

const props = defineProps<Props>()

const search = ref(props.filters.search || '')

const searchEmployees = () => {
  router.get(
    employees.index().url,
    { search: search.value },
    { preserveState: true }
  )
}

const deleteEmployee = (employee: Employee) => {
  if (
    confirm(
      `Are you sure you want to delete ${employee.full_name}? This action cannot be undone.`
    )
  ) {
    router.delete(employees.destroy(employee.id).url)
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Employees" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Employees</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Employees
          </h1>
          <p class="text-muted-foreground mt-2">
            Manage your organization's employees and their information
          </p>
        </div>
        <Link :href="employees.create().url">
          <Button class="gap-2">
            <Plus class="h-4 w-4" />
            Add Employee
          </Button>
        </Link>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-4">
        <div class="relative flex-1 max-w-md">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="search"
            placeholder="Search employees by name, email, or ID..."
            class="pl-10"
            @keyup.enter="searchEmployees"
          />
        </div>
        <Button @click="searchEmployees" variant="secondary">
          Search
        </Button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg shadow-blue-500/20 transition-all hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <UserCircle class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-blue-100">Total Employees</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.employees.total }}</p>
              <p class="text-xs text-blue-100 mt-2">
                In your organization
              </p>
            </div>
          </div>
        </div>

        <!-- Active Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <UserCircle class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-emerald-100">Active Employees</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.employees.data.filter(e => e.is_active).length }}
              </p>
              <p class="text-xs text-emerald-100 mt-2">
                Currently employed
              </p>
            </div>
          </div>
        </div>

        <!-- Inactive Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-gray-500 to-gray-600 p-6 shadow-lg shadow-gray-500/20 transition-all hover:shadow-xl hover:shadow-gray-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <UserCircle class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-100">Inactive Employees</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.employees.data.filter(e => !e.is_active).length }}
              </p>
              <p class="text-xs text-gray-100 mt-2">
                Not currently active
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
              <TableHead>Employee ID</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Department</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Hired Date</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="employee in props.employees.data" :key="employee.id">
              <TableCell class="font-medium">{{ employee.custom_id }}</TableCell>
              <TableCell>
                <Link :href="employees.show(employee.id).url" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                  <div class="h-10 w-10 rounded-full overflow-hidden bg-gradient-to-br from-primary to-primary/60 flex items-center justify-center text-white font-semibold flex-shrink-0">
                    <img
                      v-if="employee.avatar_url"
                      :src="employee.avatar_url"
                      :alt="employee.full_name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else>
                      {{ employee.first_name[0] }}{{ employee.last_name[0] }}
                    </span>
                  </div>
                  <div>
                    <div class="font-medium">{{ employee.full_name }}</div>
                    <div v-if="employee.phone" class="text-sm text-muted-foreground">
                      {{ employee.phone }}
                    </div>
                  </div>
                </Link>
              </TableCell>
              <TableCell>{{ employee.email }}</TableCell>
              <TableCell>
                <span v-if="employee.department" class="text-sm">
                  {{ employee.department.name }}
                </span>
                <span v-else class="text-sm text-muted-foreground">
                  No department
                </span>
              </TableCell>
              <TableCell>
                <Badge v-if="employee.is_active" variant="default">
                  Active
                </Badge>
                <Badge v-else variant="secondary">
                  Inactive
                </Badge>
              </TableCell>
              <TableCell>
                <span v-if="employee.hired_at" class="text-sm">
                  {{ new Date(employee.hired_at).toLocaleDateString() }}
                </span>
                <span v-else class="text-sm text-muted-foreground">-</span>
              </TableCell>
              <TableCell class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="employees.show(employee.id).url">
                    <Button variant="ghost" size="sm">
                      <Eye class="h-4 w-4" />
                    </Button>
                  </Link>
                  <Link :href="employees.edit(employee.id).url">
                    <Button variant="ghost" size="sm">
                      <Edit class="h-4 w-4" />
                    </Button>
                  </Link>
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="deleteEmployee(employee)"
                  >
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="props.employees.data.length === 0">
              <TableCell colspan="7" class="text-center py-12">
                <div class="flex flex-col items-center gap-2">
                  <UserCircle class="h-12 w-12 text-muted-foreground" />
                  <p class="text-muted-foreground">No employees found</p>
                  <Link :href="employees.create().url">
                    <Button variant="outline" size="sm" class="mt-2">
                      <Plus class="mr-2 h-4 w-4" />
                      Add your first employee
                    </Button>
                  </Link>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination -->
      <div
        v-if="props.employees.last_page > 1"
        class="flex items-center justify-between"
      >
        <p class="text-sm text-muted-foreground">
          Showing {{ (props.employees.current_page - 1) * props.employees.per_page + 1 }} to
          {{ Math.min(props.employees.current_page * props.employees.per_page, props.employees.total) }}
          of {{ props.employees.total }} employees
        </p>
        <div class="flex items-center gap-2">
          <Button
            v-for="page in props.employees.last_page"
            :key="page"
            :variant="page === props.employees.current_page ? 'default' : 'outline'"
            size="sm"
            @click="router.get(employees.index({ query: { page } }).url)"
          >
            {{ page }}
          </Button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
