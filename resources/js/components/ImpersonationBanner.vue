<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePage, router } from '@inertiajs/vue3';
import { Shield, LogOut } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const impersonating = computed(() => page.props.impersonating);

const exitImpersonation = () => {
    router.post('/exit-impersonation');
};

const formatDuration = (startedAt: string) => {
    const start = new Date(startedAt);
    const now = new Date();
    const diffMs = now.getTime() - start.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 60) {
        return `${diffMins} min${diffMins !== 1 ? 's' : ''}`;
    }
    
    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    return `${hours}h ${mins}m`;
};
</script>

<template>
    <div
        v-if="impersonating"
        class="bg-yellow-500 text-yellow-950 px-4 py-2 flex items-center justify-between border-b border-yellow-600"
    >
        <div class="flex items-center gap-3">
            <Shield class="h-5 w-5" />
            <div class="flex flex-col">
                <span class="text-sm font-semibold">
                    Super Admin Impersonation Active
                </span>
                <span class="text-xs opacity-90">
                    Session started {{ formatDuration(impersonating.started_at) }} ago
                </span>
            </div>
        </div>
        <Button
            @click="exitImpersonation"
            variant="secondary"
            size="sm"
            class="bg-yellow-950 text-yellow-50 hover:bg-yellow-900"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Exit Impersonation
        </Button>
    </div>
</template>
