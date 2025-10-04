<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Save, X, Upload, Trash2, UserCircle, Briefcase, Clock } from 'lucide-vue-next'
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
  email: string
  phone: string | null
  avatar: string | null
  avatar_url: string | null
  department_id: number | null
  is_active: boolean
  hired_at: string | null
}

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
}

interface Props {
  employee?: Employee
  departments: Department[]
  shifts: Shift[]
}

const props = defineProps<Props>()

const isEdit = computed(() => !!props.employee)

const form = useForm({
  custom_id: props.employee?.custom_id || '',
  first_name: props.employee?.first_name || '',
  last_name: props.employee?.last_name || '',
  email: props.employee?.email || '',
  phone: props.employee?.phone || '',
  avatar: null as File | null,
  department_id: props.employee?.department_id || null,
  shift_id: null as number | null,
  shift_effective_from: '',
  shift_effective_to: '',
  is_active: props.employee?.is_active ?? true,
  hired_at: props.employee?.hired_at || '',
  _method: 'PUT' as string,
})

const avatarPreview = ref<string | null>(props.employee?.avatar_url || null)
const fileInput = ref<HTMLInputElement | null>(null)

const handleAvatarChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (file) {
    form.avatar = file
    const reader = new FileReader()
    reader.onload = (e) => {
      avatarPreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const removeAvatar = () => {
  form.avatar = null
  avatarPreview.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const submit = () => {
  if (isEdit.value) {
    form.post(employees.update(props.employee!.id).url)
  } else {
    form.post(employees.store().url)
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit Employee' : 'Create Employee'" />

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
          :href="employees.index().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Employees
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">{{ isEdit ? 'Edit' : 'Create' }}</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            {{ isEdit ? 'Edit Employee' : 'Add New Employee' }}
          </h1>
          <p class="text-muted-foreground mt-2">
            {{ isEdit ? 'Update employee information' : 'Add a new employee to your organization' }}
          </p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="max-w-4xl space-y-6">
        <div class="rounded-xl border bg-card p-8 shadow-sm space-y-8">
          <!-- Avatar Upload -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-blue-500 to-violet-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-violet-500">
                <Upload class="h-4 w-4 text-white" />
              </div>
              Profile Picture
            </h3>
            <div class="flex items-start gap-6">
              <div class="flex-shrink-0">
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-blue-100 to-violet-100 dark:from-blue-900 dark:to-violet-900 overflow-hidden shadow-lg ring-4 ring-background">
                  <img
                    v-if="avatarPreview"
                    :src="avatarPreview"
                    alt="Avatar preview"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground">
                    <Upload class="w-10 h-10" />
                  </div>
                </div>
              </div>
              <div class="flex-1 space-y-2">
                <Label for="avatar">Upload Photo</Label>
                <div class="flex gap-2">
                  <Input
                    id="avatar"
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    @change="handleAvatarChange"
                    class="flex-1"
                  />
                  <Button
                    v-if="avatarPreview"
                    type="button"
                    variant="outline"
                    size="icon"
                    @click="removeAvatar"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
                <p v-if="form.errors.avatar" class="text-sm text-destructive">
                  {{ form.errors.avatar }}
                </p>
                <p class="text-xs text-muted-foreground">
                  JPG, PNG or WebP. Max size 2MB.
                </p>
              </div>
            </div>
          </div>

          <!-- Basic Information -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-emerald-500 to-green-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-emerald-500 to-green-500">
                <UserCircle class="h-4 w-4 text-white" />
              </div>
              Basic Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <Label for="custom_id" class="text-sm font-medium">
                  Employee ID <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="custom_id"
                  v-model="form.custom_id"
                  placeholder="e.g. EMP001"
                  :disabled="isEdit"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.custom_id" class="text-sm text-destructive">
                  {{ form.errors.custom_id }}
                </p>
                <p v-if="!isEdit" class="text-xs text-muted-foreground">
                  Unique identifier for the employee (cannot be changed later)
                </p>
              </div>

              <div class="space-y-2">
                <Label for="email" class="text-sm font-medium">
                  Email Address <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  placeholder="employee@example.com"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.email" class="text-sm text-destructive">
                  {{ form.errors.email }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="first_name" class="text-sm font-medium">
                  First Name <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="first_name"
                  v-model="form.first_name"
                  placeholder="John"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.first_name" class="text-sm text-destructive">
                  {{ form.errors.first_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="last_name" class="text-sm font-medium">
                  Last Name <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="last_name"
                  v-model="form.last_name"
                  placeholder="Doe"
                  required
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.last_name" class="text-sm text-destructive">
                  {{ form.errors.last_name }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="phone" class="text-sm font-medium">Phone Number</Label>
                <Input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  placeholder="+1 (555) 123-4567"
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.phone" class="text-sm text-destructive">
                  {{ form.errors.phone }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="hired_at" class="text-sm font-medium">Hire Date</Label>
                <Input
                  id="hired_at"
                  v-model="form.hired_at"
                  type="date"
                  class="transition-all focus:ring-2 focus:ring-emerald-500/20"
                />
                <p v-if="form.errors.hired_at" class="text-sm text-destructive">
                  {{ form.errors.hired_at }}
                </p>
              </div>
            </div>
          </div>

          <!-- Department & Status -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-violet-500 to-purple-500">
                <Briefcase class="h-4 w-4 text-white" />
              </div>
              Department & Status
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="department_id">Department</Label>
                <select
                  id="department_id"
                  v-model="form.department_id"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                  <option :value="null">No Department</option>
                  <option
                    v-for="dept in departments"
                    :key="dept.id"
                    :value="dept.id"
                  >
                    {{ dept.name }}
                  </option>
                </select>
                <p v-if="form.errors.department_id" class="text-sm text-destructive">
                  {{ form.errors.department_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="is_active">Employment Status</Label>
                <div class="flex items-center space-x-2 h-10">
                  <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input"
                  />
                  <Label for="is_active" class="cursor-pointer">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                  </Label>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ form.is_active ? 'Employee is currently employed' : 'Employee is not active' }}
                </p>
              </div>
            </div>
          </div>

          <!-- Shift Assignment -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-orange-500 to-amber-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-orange-500 to-amber-500">
                <Clock class="h-4 w-4 text-white" />
              </div>
              Shift Assignment <span class="text-sm font-normal text-muted-foreground">(Optional)</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="space-y-2">
                <Label for="shift_id">Assign Shift</Label>
                <select
                  id="shift_id"
                  v-model="form.shift_id"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                  <option :value="null">No Shift</option>
                  <option
                    v-for="shift in shifts"
                    :key="shift.id"
                    :value="shift.id"
                  >
                    {{ shift.name }} ({{ shift.start_time.slice(0, 5) }} - {{ shift.end_time.slice(0, 5) }})
                  </option>
                </select>
                <p v-if="form.errors.shift_id" class="text-sm text-destructive">
                  {{ form.errors.shift_id }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="shift_effective_from">Effective From</Label>
                <Input
                  id="shift_effective_from"
                  v-model="form.shift_effective_from"
                  type="date"
                  :disabled="!form.shift_id"
                />
                <p v-if="form.errors.shift_effective_from" class="text-sm text-destructive">
                  {{ form.errors.shift_effective_from }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="shift_effective_to">Effective To</Label>
                <Input
                  id="shift_effective_to"
                  v-model="form.shift_effective_to"
                  type="date"
                  :disabled="!form.shift_id"
                />
                <p v-if="form.errors.shift_effective_to" class="text-sm text-destructive">
                  {{ form.errors.shift_effective_to }}
                </p>
                <p class="text-xs text-muted-foreground">
                  Leave empty for ongoing assignment
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="rounded-xl border bg-gradient-to-r from-gray-50 to-white dark:from-gray-900 dark:to-gray-950 p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
              <span class="text-destructive">*</span> Required fields
            </p>
            <div class="flex items-center gap-3">
              <Link :href="employees.index().url">
                <Button type="button" variant="outline" class="gap-2">
                  <X class="h-4 w-4" />
                  Cancel
                </Button>
              </Link>
              <Button
                type="submit"
                :disabled="form.processing"
                class="gap-2 bg-gradient-to-r from-blue-500 to-violet-500 hover:from-blue-600 hover:to-violet-600 shadow-lg shadow-blue-500/30"
              >
                <Save class="h-4 w-4" />
                {{ isEdit ? 'Update Employee' : 'Create Employee' }}
              </Button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
