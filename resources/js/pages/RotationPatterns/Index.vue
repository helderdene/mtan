<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import RotationBadge from '@/components/RotationBadge.vue'
import { Plus, Search, Edit, Trash2, Eye, Users } from 'lucide-vue-next'
import { dashboard } from '@/routes'

interface Shift {
  id: number
  name: string
  start_time: string
  end_time: string
  color_code?: string
}

interface RotationPattern {
  id: number
  name: string
  description: string | null
  cycle_type: 'daily' | 'weekly' | 'monthly'
  cycle_duration: number
  sequence: Array<{
    shift_id: number
    duration_days: number
  }>
  shifts?: Shift[]
  employees_count: number
  is_active: boolean
  created_at: string
}

interface Props {
  patterns: RotationPattern[]
}

const props = defineProps<Props>()

const searchQuery = ref('')

const deletePattern = (id: number) => {
  if (!confirm('Are you sure you want to delete this rotation pattern? This will not affect currently assigned employees.')) {
    return
  }

  router.delete(`/rotation-patterns/${id}`)
}

const filteredPatterns = ref(props.patterns)

const handleSearch = () => {
  if (!searchQuery.value) {
    filteredPatterns.value = props.patterns
    return
  }

  filteredPatterns.value = props.patterns.filter(pattern =>
    pattern.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    pattern.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
}
</script>

<template>
  <AppLayout>
    <Head title="Rotation Patterns" />

    <template #breadcrumbs>
      <div class="flex items-center space-x-2 text-sm">
        <Link
          :href="dashboard().url"
          class="text-muted-foreground hover:text-foreground transition-colors"
        >
          Dashboard
        </Link>
        <span class="text-muted-foreground">/</span>
        <span class="font-medium">Rotation Patterns</span>
      </div>
    </template>

    <div class="space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-gray-900 to-gray-600 dark:from-gray-100 dark:to-gray-400 bg-clip-text text-transparent">
            Rotation Patterns
          </h1>
          <p class="text-muted-foreground mt-2">
            Manage shift rotation schedules for employees
          </p>
        </div>
        <Link href="/rotation-patterns/create">
          <Button class="gap-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 shadow-lg shadow-blue-500/30">
            <Plus class="w-4 h-4" />
            Create Rotation Pattern
          </Button>
        </Link>
      </div>

      <!-- Search -->
      <div class="flex items-center gap-4">
        <div class="relative flex-1 max-w-md">
          <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            type="text"
            placeholder="Search rotation patterns..."
            class="pl-10"
            @input="handleSearch"
          />
        </div>
      </div>

      <!-- Patterns List -->
      <div v-if="filteredPatterns.length === 0" class="text-center py-12 border-2 border-dashed rounded-xl">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-muted/50 mb-4">
          <Users class="h-8 w-8 text-muted-foreground/50" />
        </div>
        <h3 class="text-lg font-semibold mb-2">No rotation patterns found</h3>
        <p class="text-sm text-muted-foreground mb-6">
          {{ searchQuery ? 'Try adjusting your search' : 'Get started by creating your first rotation pattern' }}
        </p>
        <Link v-if="!searchQuery" href="/rotation-patterns/create">
          <Button>
            <Plus class="w-4 h-4 mr-2" />
            Create Rotation Pattern
          </Button>
        </Link>
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="pattern in filteredPatterns"
          :key="pattern.id"
          class="group relative bg-card rounded-xl border p-6 shadow-sm hover:shadow-md transition-all"
        >
          <div class="flex items-start justify-between">
            <!-- Left Section -->
            <div class="flex-1 space-y-3">
              <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold">{{ pattern.name }}</h3>
                <RotationBadge
                  :rotation-name="pattern.cycle_type"
                  :cycle-type="pattern.cycle_type"
                  :total-positions="pattern.sequence.length"
                  variant="compact"
                />
                <div
                  v-if="!pattern.is_active"
                  class="px-2 py-0.5 text-xs font-medium rounded bg-muted text-muted-foreground"
                >
                  Inactive
                </div>
              </div>

              <p v-if="pattern.description" class="text-sm text-muted-foreground">
                {{ pattern.description }}
              </p>

              <div class="flex items-center gap-6 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                  <Users class="h-4 w-4" />
                  <span>{{ pattern.employees_count }} employee{{ pattern.employees_count !== 1 ? 's' : '' }}</span>
                </div>
                <div class="text-muted-foreground">
                  {{ pattern.sequence.length }} shift{{ pattern.sequence.length !== 1 ? 's' : '' }} in sequence
                </div>
                <div class="text-muted-foreground">
                  {{ pattern.cycle_duration }} {{ pattern.cycle_type === 'daily' ? 'day' : pattern.cycle_type === 'weekly' ? 'week' : 'month' }} cycle
                </div>
              </div>

              <!-- Shift Sequence Preview -->
              <div v-if="pattern.shifts" class="flex flex-wrap gap-2 pt-2">
                <div
                  v-for="(item, index) in pattern.sequence.slice(0, 5)"
                  :key="index"
                  class="flex items-center gap-1.5 px-2 py-1 rounded text-white text-xs font-medium"
                  :style="{ backgroundColor: pattern.shifts.find(s => s.id === item.shift_id)?.color_code || '#3b82f6' }"
                >
                  <span>{{ index + 1 }}.</span>
                  <span>{{ pattern.shifts.find(s => s.id === item.shift_id)?.name }}</span>
                  <span class="opacity-75">({{ item.duration_days }}d)</span>
                </div>
                <div
                  v-if="pattern.sequence.length > 5"
                  class="flex items-center px-2 py-1 rounded bg-muted text-muted-foreground text-xs"
                >
                  +{{ pattern.sequence.length - 5 }} more
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <Link :href="`/rotation-patterns/${pattern.id}`">
                <Button size="sm" variant="ghost">
                  <Eye class="h-4 w-4" />
                </Button>
              </Link>
              <Link :href="`/rotation-patterns/${pattern.id}/edit`">
                <Button size="sm" variant="ghost">
                  <Edit class="h-4 w-4" />
                </Button>
              </Link>
              <Button
                size="sm"
                variant="ghost"
                class="text-destructive hover:text-destructive hover:bg-destructive/10"
                @click="deletePattern(pattern.id)"
              >
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
