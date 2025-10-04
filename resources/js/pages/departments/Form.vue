<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Save, X, Briefcase } from 'lucide-vue-next'
import departments from '@/routes/departments'
import { dashboard } from '@/routes'

interface Department {
  id: number
  name: string
}

interface Props {
  department?: Department
}

const props = defineProps<Props>()

const isEdit = computed(() => !!props.department)

const form = useForm({
  name: props.department?.name || '',
})

const submit = () => {
  if (isEdit.value) {
    form.put(departments.update(props.department!.id).url)
  } else {
    form.post(departments.store().url)
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit Department' : 'Create Department'" />

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
          :href="departments.index().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Departments
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
            {{ isEdit ? 'Edit Department' : 'Add New Department' }}
          </h1>
          <p class="text-muted-foreground mt-2">
            {{ isEdit ? 'Update department information' : 'Create a new department in your organization' }}
          </p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="max-w-3xl space-y-6">
        <div class="rounded-xl border bg-card p-8 shadow-sm space-y-8">
          <!-- Department Information -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-500 rounded-full opacity-10 blur-2xl"></div>
            <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-gradient-to-br from-violet-500 to-purple-500">
                <Briefcase class="h-4 w-4 text-white" />
              </div>
              Department Information
            </h3>
            <div class="space-y-2">
              <Label for="name" class="text-sm font-medium">
                Department Name <span class="text-destructive">*</span>
              </Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="e.g. Engineering, Sales, Marketing"
                required
                autofocus
                class="transition-all focus:ring-2 focus:ring-violet-500/20"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">
                {{ form.errors.name }}
              </p>
              <p class="text-xs text-muted-foreground">
                A descriptive name for the department
              </p>
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
              <Link :href="departments.index().url">
                <Button type="button" variant="outline" class="gap-2">
                  <X class="h-4 w-4" />
                  Cancel
                </Button>
              </Link>
              <Button
                type="submit"
                :disabled="form.processing"
                class="gap-2 bg-gradient-to-r from-violet-500 to-purple-500 hover:from-violet-600 hover:to-purple-600 shadow-lg shadow-violet-500/30"
              >
                <Save class="h-4 w-4" />
                {{ isEdit ? 'Update Department' : 'Create Department' }}
              </Button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
