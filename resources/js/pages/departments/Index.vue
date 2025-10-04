<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Plus, Edit, Trash2, Briefcase } from 'lucide-vue-next'
import departments from '@/routes/departments'
import { dashboard } from '@/routes'

interface Department {
  id: number
  name: string
  employees_count: number
  created_at: string
}

interface Props {
  departments: Department[]
}

const props = defineProps<Props>()

const deleteDepartment = (department: Department) => {
  if (department.employees_count > 0) {
    alert(`Cannot delete ${department.name} because it has ${department.employees_count} employee(s). Please reassign them first.`)
    return
  }

  if (confirm(`Are you sure you want to delete ${department.name}?`)) {
    router.delete(departments.destroy(department.id).url)
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Departments" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Departments</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Departments
          </h1>
          <p class="text-muted-foreground mt-2">
            Manage organizational departments and structure
          </p>
        </div>
        <Link :href="departments.create().url">
          <Button class="gap-2">
            <Plus class="h-4 w-4" />
            Add Department
          </Button>
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Departments Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 p-6 shadow-lg shadow-violet-500/20 transition-all hover:shadow-xl hover:shadow-violet-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Briefcase class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-violet-100">Total Departments</p>
              <p class="text-3xl font-bold text-white mt-2">{{ props.departments.length }}</p>
              <p class="text-xs text-violet-100 mt-2">
                In organization
              </p>
            </div>
          </div>
        </div>

        <!-- Total Employees Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg shadow-blue-500/20 transition-all hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Briefcase class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-blue-100">Total Employees</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.departments.reduce((sum, d) => sum + d.employees_count, 0) }}
              </p>
              <p class="text-xs text-blue-100 mt-2">
                Across all departments
              </p>
            </div>
          </div>
        </div>

        <!-- Average Card -->
        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30 hover:-translate-y-1">
          <div class="absolute top-0 right-0 -mt-4 -mr-4">
            <div class="h-24 w-24 rounded-full bg-white/10 backdrop-blur-sm" />
          </div>
          <div class="relative">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 rounded-lg bg-white/20 backdrop-blur-sm">
                <Briefcase class="h-6 w-6 text-white" />
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-emerald-100">Avg. per Department</p>
              <p class="text-3xl font-bold text-white mt-2">
                {{ props.departments.length > 0
                  ? Math.round(props.departments.reduce((sum, d) => sum + d.employees_count, 0) / props.departments.length)
                  : 0
                }}
              </p>
              <p class="text-xs text-emerald-100 mt-2">
                Employees per dept
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
              <TableHead>Department Name</TableHead>
              <TableHead>Employees</TableHead>
              <TableHead>Created Date</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="department in props.departments" :key="department.id">
              <TableCell>
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/60 flex items-center justify-center">
                    <Briefcase class="h-5 w-5 text-white" />
                  </div>
                  <div class="font-medium">{{ department.name }}</div>
                </div>
              </TableCell>
              <TableCell>
                <div class="flex items-center gap-2">
                  <span class="text-sm">{{ department.employees_count }}</span>
                  <span class="text-xs text-muted-foreground">employee{{ department.employees_count !== 1 ? 's' : '' }}</span>
                </div>
              </TableCell>
              <TableCell>
                <span class="text-sm">
                  {{ new Date(department.created_at).toLocaleDateString() }}
                </span>
              </TableCell>
              <TableCell class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link :href="departments.edit(department.id).url">
                    <Button variant="ghost" size="sm">
                      <Edit class="h-4 w-4" />
                    </Button>
                  </Link>
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="deleteDepartment(department)"
                  >
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="props.departments.length === 0">
              <TableCell colspan="4" class="text-center py-12">
                <div class="flex flex-col items-center gap-2">
                  <Briefcase class="h-12 w-12 text-muted-foreground" />
                  <p class="text-muted-foreground">No departments found</p>
                  <Link :href="departments.create().url">
                    <Button variant="outline" size="sm" class="mt-2">
                      <Plus class="mr-2 h-4 w-4" />
                      Add your first department
                    </Button>
                  </Link>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template>
