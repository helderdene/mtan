<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Search, User } from 'lucide-vue-next'
import { ref, watch } from 'vue'

interface Employee {
  id: number
  custom_id: string
  full_name: string
  department?: {
    name: string
  }
}

interface Props {
  open: boolean
  selectedCount: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
  confirm: [employeeId: number, notes: string]
}>()

const searchQuery = ref('')
const searchResults = ref<Employee[]>([])
const selectedEmployee = ref<Employee | null>(null)
const notes = ref('')
const isSearching = ref(false)

// Watch for dialog open/close to reset state
watch(() => props.open, (isOpen) => {
  if (!isOpen) {
    searchQuery.value = ''
    searchResults.value = []
    selectedEmployee.value = null
    notes.value = ''
  }
})

const searchEmployees = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }

  isSearching.value = true
  try {
    const response = await fetch(`/api/v1/employees/search?q=${encodeURIComponent(searchQuery.value)}`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })
    const data = await response.json()
    searchResults.value = data.data || []
  } catch (error) {
    console.error('Employee search failed:', error)
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

const selectEmployee = (employee: Employee) => {
  selectedEmployee.value = employee
  searchQuery.value = employee.full_name
  searchResults.value = []
}

const handleConfirm = () => {
  if (!selectedEmployee.value) {
    alert('Please select an employee')
    return
  }

  emit('confirm', selectedEmployee.value.id, notes.value)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('close')">
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>Match to Employee</DialogTitle>
        <p class="text-sm text-muted-foreground">
          Match {{ selectedCount }} stranger log{{ selectedCount > 1 ? 's' : '' }} to an employee
        </p>
      </DialogHeader>

      <div class="space-y-4 py-4">
        <!-- Employee Search -->
        <div class="space-y-2">
          <Label for="employee-search">Search Employee</Label>
          <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <Input
              id="employee-search"
              v-model="searchQuery"
              @input="searchEmployees"
              placeholder="Search by name or employee ID..."
              class="pl-9"
            />
          </div>

          <!-- Search Results Dropdown -->
          <div
            v-if="searchResults.length > 0"
            class="border rounded-md max-h-60 overflow-y-auto"
          >
            <button
              v-for="employee in searchResults"
              :key="employee.id"
              @click="selectEmployee(employee)"
              class="w-full px-4 py-3 text-left hover:bg-accent transition-colors flex items-center justify-between"
            >
              <div class="flex items-center gap-3">
                <User class="h-5 w-5 text-muted-foreground" />
                <div>
                  <div class="font-medium">{{ employee.full_name }}</div>
                  <div class="text-sm text-muted-foreground">{{ employee.custom_id }}</div>
                </div>
              </div>
              <Badge v-if="employee.department" variant="outline">
                {{ employee.department.name }}
              </Badge>
            </button>
          </div>

          <!-- No Results -->
          <div
            v-if="searchQuery.length >= 2 && searchResults.length === 0 && !isSearching"
            class="text-sm text-muted-foreground text-center py-4 border rounded-md"
          >
            No employees found
          </div>

          <!-- Searching Indicator -->
          <div
            v-if="isSearching"
            class="text-sm text-muted-foreground text-center py-4 border rounded-md"
          >
            Searching...
          </div>
        </div>

        <!-- Selected Employee Display -->
        <div v-if="selectedEmployee" class="p-4 border rounded-md bg-muted/50">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                <User class="h-5 w-5 text-primary" />
              </div>
              <div>
                <div class="font-medium">{{ selectedEmployee.full_name }}</div>
                <div class="text-sm text-muted-foreground">{{ selectedEmployee.custom_id }}</div>
              </div>
            </div>
            <Badge variant="default">Selected</Badge>
          </div>
        </div>

        <!-- Notes -->
        <div class="space-y-2">
          <Label for="notes">Notes (Optional)</Label>
          <textarea
            id="notes"
            v-model="notes"
            placeholder="Add any notes about this match..."
            rows="3"
            class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          />
          <p class="text-xs text-muted-foreground">
            Notes will be attached to all {{ selectedCount }} stranger log{{ selectedCount > 1 ? 's' : '' }}
          </p>
        </div>
      </div>

      <DialogFooter>
        <Button @click="emit('close')" variant="outline">
          Cancel
        </Button>
        <Button @click="handleConfirm" :disabled="!selectedEmployee">
          Confirm Match
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
