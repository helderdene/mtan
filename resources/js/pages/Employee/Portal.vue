<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AttendanceCalendar from '@/components/Employee/AttendanceCalendar.vue'
import ViolationsList from '@/components/Employee/ViolationsList.vue'
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from '@/components/ui/tabs'

defineOptions({
  layout: AppLayout,
})

const activeTab = ref('calendar')
</script>

<template>
  <AppLayout
    :breadcrumbs="[
      { label: 'Dashboard', href: '/dashboard' },
      { label: 'My Attendance', href: '/employee/portal' },
    ]"
  >
    <div class="space-y-6">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">My Attendance Portal</h1>
        <p class="text-muted-foreground mt-2">
          View your attendance history, work hours, and manage violations
        </p>
      </div>

      <TabsRoot v-model="activeTab" class="space-y-6">
        <TabsList class="grid w-full grid-cols-2 lg:w-[400px]">
          <TabsTrigger value="calendar">
            Calendar
          </TabsTrigger>
          <TabsTrigger value="violations">
            Violations
          </TabsTrigger>
        </TabsList>

        <TabsContent value="calendar" class="space-y-6">
          <AttendanceCalendar />
        </TabsContent>

        <TabsContent value="violations" class="space-y-6">
          <ViolationsList />
        </TabsContent>
      </TabsRoot>
    </div>
  </AppLayout>
</template>
