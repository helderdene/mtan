<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Edit,
  Trash2,
  Clock,
  Users,
  Plus,
  UserPlus,
} from 'lucide-vue-next'
import shiftsRoute from '@/routes/shifts'
import employees from '@/routes/employees'
import { dashboard } from '@/routes'

interface Department {
  id: number
  name: string
}

interface Employee {
  id: number
  custom_id: string
  first_name: string
  last_name: string
  full_name: string
  email: string
  department: Department | null
  pivot: {
    effective_from: string
    effective_to: string | null
  }
}

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  break_start: string | null
  break_end: string | null
  working_days: string[]
  is_default: boolean
  employees: Employee[]
}

interface Props {
  shift: Shift
  available_employees: Employee[]
}

const props = defineProps<Props>()

const showBulkAssignDialog = ref(false)
const selectedEmployees = ref<number[]>([])

const bulkAssignForm = useForm({
  employee_ids: [] as number[],
  effective_from: '',
  effective_to: '',
})

const formatTime = (time: string) => {
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${minutes} ${ampm}`
}

const formatWorkingDays = (days: string[]) => {
  const shortDays = days.map(day => day.substring(0, 3).toUpperCase())
  return shortDays.join(', ')
}

const toggleEmployee = (employeeId: number) => {
  const index = selectedEmployees.value.indexOf(employeeId)
  if (index === -1) {
    selectedEmployees.value.push(employeeId)
  } else {
    selectedEmployees.value.splice(index, 1)
  }
}

const selectAll = () => {
  if (selectedEmployees.value.length === props.available_employees.length) {
    selectedEmployees.value = []
  } else {
    selectedEmployees.value = props.available_employees.map(e => e.id)
  }
}

const assignEmployees = () => {
  bulkAssignForm.employee_ids = selectedEmployees.value
  bulkAssignForm.post(shiftsRoute.assignEmployees(props.shift.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      showBulkAssignDialog.value = false
      selectedEmployees.value = []
      bulkAssignForm.reset()
    },
  })
}

const removeEmployee = (employeeId: number) => {
  if (confirm('Are you sure you want to remove this employee from the shift?')) {
    router.delete(`/employees/${employeeId}/shifts/${props.shift.id}`, {
      preserveScroll: true,
    })
  }
}

const deleteShift = () => {
  if (confirm(`Are you sure you want to delete ${props.shift.name}? This action cannot be undone.`)) {
    router.delete(shiftsRoute.destroy(props.shift.id).url)
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="shift.name" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <Link
          :href="shiftsRoute.index().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Shifts
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ shift.name }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
              {{ shift.name }}
            </h1>
            <Badge v-if="shift.is_default" variant="default">Default</Badge>
          </div>
          <p class="text-muted-foreground">
            {{ shift.employees.length }} employee{{ shift.employees.length !== 1 ? 's' : '' }} assigned
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="shiftsRoute.edit(shift.id).url">
            <Button class="gap-2">
              <Edit class="h-4 w-4" />
              Edit
            </Button>
          </Link>
          <Button variant="destructive" @click="deleteShift" class="gap-2">
            <Trash2 class="h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <!-- Shift Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Time Information -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
          <h2 class="text-xl font-semibold mb-6">Shift Times</h2>
          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <Clock class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Work Hours</p>
                <p class="font-medium">
                  {{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}
                </p>
              </div>
            </div>
            <div v-if="shift.break_start && shift.break_end" class="flex items-center gap-3">
              <Clock class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Break Time</p>
                <p class="font-medium">
                  {{ formatTime(shift.break_start) }} - {{ formatTime(shift.break_end) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Working Days -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
          <h2 class="text-xl font-semibold mb-6">Working Days</h2>
          <div class="font-mono text-sm bg-muted p-3 rounded">
            {{ formatWorkingDays(shift.working_days) }}
          </div>
        </div>
      </div>

      <!-- Assigned Employees -->
      <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold">Assigned Employees</h2>
          <Dialog v-model:open="showBulkAssignDialog">
            <DialogTrigger as-child>
              <Button>
                <UserPlus class="w-4 h-4 mr-2" />
                Assign Employees
              </Button>
            </DialogTrigger>
            <DialogContent class="max-w-2xl max-h-[80vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Assign Employees to {{ shift.name }}</DialogTitle>
                <DialogDescription>
                  Select employees and set effective dates
                </DialogDescription>
              </DialogHeader>
              <form @submit.prevent="assignEmployees" class="space-y-4">
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <Label>Select Employees</Label>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      @click="selectAll"
                    >
                      {{ selectedEmployees.length === available_employees.length ? 'Deselect All' : 'Select All' }}
                    </Button>
                  </div>
                  <div class="border rounded-lg max-h-60 overflow-y-auto">
                    <div
                      v-for="employee in available_employees"
                      :key="employee.id"
                      class="flex items-center gap-3 p-3 border-b last:border-b-0 hover:bg-muted/50"
                    >
                      <Checkbox
                        :id="`emp-${employee.id}`"
                        :checked="selectedEmployees.includes(employee.id)"
                        @update:checked="toggleEmployee(employee.id)"
                      />
                      <Label :for="`emp-${employee.id}`" class="flex-1 cursor-pointer">
                        <div class="font-medium">{{ employee.full_name }}</div>
                        <div class="text-xs text-muted-foreground">
                          {{ employee.custom_id }} • {{ employee.department?.name || 'No department' }}
                        </div>
                      </Label>
                    </div>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    {{ selectedEmployees.length }} employee(s) selected
                  </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <Label for="effective_from">Effective From *</Label>
                    <Input
                      id="effective_from"
                      v-model="bulkAssignForm.effective_from"
                      type="date"
                      required
                    />
                    <p v-if="bulkAssignForm.errors.effective_from" class="text-sm text-destructive">
                      {{ bulkAssignForm.errors.effective_from }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <Label for="effective_to">Effective To</Label>
                    <Input
                      id="effective_to"
                      v-model="bulkAssignForm.effective_to"
                      type="date"
                    />
                    <p v-if="bulkAssignForm.errors.effective_to" class="text-sm text-destructive">
                      {{ bulkAssignForm.errors.effective_to }}
                    </p>
                  </div>
                </div>

                <div class="flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    @click="showBulkAssignDialog = false"
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    :disabled="bulkAssignForm.processing || selectedEmployees.length === 0"
                  >
                    Assign {{ selectedEmployees.length }} Employee(s)
                  </Button>
                </div>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        <div v-if="shift.employees.length > 0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Employee ID</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Department</TableHead>
                <TableHead>Effective From</TableHead>
                <TableHead>Effective To</TableHead>
                <TableHead class="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="employee in shift.employees" :key="employee.id">
                <TableCell class="font-medium">{{ employee.custom_id }}</TableCell>
                <TableCell>
                  <Link
                    :href="employees.show(employee.id).url"
                    class="hover:underline"
                  >
                    {{ employee.full_name }}
                  </Link>
                </TableCell>
                <TableCell>
                  {{ employee.department?.name || '-' }}
                </TableCell>
                <TableCell>
                  {{ new Date(employee.pivot.effective_from).toLocaleDateString() }}
                </TableCell>
                <TableCell>
                  <Badge v-if="!employee.pivot.effective_to" variant="default">
                    Current
                  </Badge>
                  <span v-else>
                    {{ new Date(employee.pivot.effective_to).toLocaleDateString() }}
                  </span>
                </TableCell>
                <TableCell class="text-right">
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="removeEmployee(employee.id)"
                  >
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
        <p v-else class="text-muted-foreground text-center py-8">
          No employees assigned to this shift
        </p>
      </div>
    </div>
  </AppLayout>
</template>
