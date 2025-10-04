<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Users, Database, TrendingUp, Activity, Building2, CheckCircle2, XCircle, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

interface Metrics {
    tenants: {
        total: number;
        active: number;
        inactive: number;
    };
    devices: {
        total: number;
        active: number;
        inactive: number;
    };
    subscription_breakdown: Record<string, number>;
    recent_tenants: Array<{
        id: string;
        company_name: string;
        subdomain: string;
        subscription_plan: string;
        is_active: boolean;
        created_at: string;
    }>;
}

const props = defineProps<{
    metrics: Metrics;
}>();

const subscriptionPlans = [
    {
        name: 'trial',
        label: 'Trial',
        color: 'text-slate-600 dark:text-slate-400',
        bgColor: 'bg-slate-100 dark:bg-slate-900',
        progressColor: 'bg-slate-500'
    },
    {
        name: 'basic',
        label: 'Basic',
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950',
        progressColor: 'bg-blue-500'
    },
    {
        name: 'professional',
        label: 'Professional',
        color: 'text-purple-600 dark:text-purple-400',
        bgColor: 'bg-purple-50 dark:bg-purple-950',
        progressColor: 'bg-purple-500'
    },
    {
        name: 'enterprise',
        label: 'Enterprise',
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950',
        progressColor: 'bg-amber-500'
    },
];

const tenantActivePercentage = computed(() =>
    props.metrics.tenants.total > 0
        ? Math.round((props.metrics.tenants.active / props.metrics.tenants.total) * 100)
        : 0
);

const deviceActivePercentage = computed(() =>
    props.metrics.devices.total > 0
        ? Math.round((props.metrics.devices.active / props.metrics.devices.total) * 100)
        : 0
);

const totalSubscriptions = computed(() =>
    Object.values(props.metrics.subscription_breakdown).reduce((sum, count) => sum + count, 0)
);

const getSubscriptionPercentage = (count: number) => {
    return totalSubscriptions.value > 0
        ? Math.round((count / totalSubscriptions.value) * 100)
        : 0;
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    return date.toLocaleDateString();
};
</script>

<template>
    <SuperAdminLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }]">
        <Head title="Super Admin Dashboard" />

        <div class="space-y-8 p-6 md:p-8">
            <!-- Header -->
            <div class="space-y-2">
                <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                    Dashboard
                </h1>
                <p class="text-base text-muted-foreground">
                    System-wide overview and real-time metrics
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Total Tenants Card -->
                <Card class="border-none shadow-lg hover:shadow-xl transition-shadow duration-300 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-950/50 dark:to-blue-900/30">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                            Total Tenants
                        </CardTitle>
                        <div class="p-2.5 rounded-xl bg-blue-500/10 dark:bg-blue-500/20">
                            <Building2 class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-1.5">
                        <div class="text-3xl font-bold text-blue-900 dark:text-blue-50">
                            {{ metrics.tenants.total }}
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <CheckCircle2 class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                            <span class="text-muted-foreground">
                                <span class="font-semibold text-green-600 dark:text-green-400">{{ metrics.tenants.active }}</span> active
                            </span>
                            <span class="text-muted-foreground">•</span>
                            <XCircle class="h-3.5 w-3.5 text-red-500 dark:text-red-400" />
                            <span class="text-muted-foreground">
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ metrics.tenants.inactive }}</span> inactive
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Active Tenants Card -->
                <Card class="border-none shadow-lg hover:shadow-xl transition-shadow duration-300 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-950/50 dark:to-emerald-900/30">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                            Active Tenants
                        </CardTitle>
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20">
                            <Activity class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="text-3xl font-bold text-emerald-900 dark:text-emerald-50">
                            {{ metrics.tenants.active }}
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Activity Rate</span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ tenantActivePercentage }}%
                                </span>
                            </div>
                            <div class="h-2 bg-emerald-100 dark:bg-emerald-950 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full transition-all duration-500"
                                    :style="{ width: `${tenantActivePercentage}%` }"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Total Devices Card -->
                <Card class="border-none shadow-lg hover:shadow-xl transition-shadow duration-300 bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-950/50 dark:to-purple-900/30">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle class="text-sm font-semibold text-purple-900 dark:text-purple-100">
                            Total Devices
                        </CardTitle>
                        <div class="p-2.5 rounded-xl bg-purple-500/10 dark:bg-purple-500/20">
                            <Database class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-1.5">
                        <div class="text-3xl font-bold text-purple-900 dark:text-purple-50">
                            {{ metrics.devices.total }}
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <CheckCircle2 class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                            <span class="text-muted-foreground">
                                <span class="font-semibold text-green-600 dark:text-green-400">{{ metrics.devices.active }}</span> online
                            </span>
                            <span class="text-muted-foreground">•</span>
                            <XCircle class="h-3.5 w-3.5 text-red-500 dark:text-red-400" />
                            <span class="text-muted-foreground">
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ metrics.devices.inactive }}</span> offline
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Active Devices Card -->
                <Card class="border-none shadow-lg hover:shadow-xl transition-shadow duration-300 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-950/50 dark:to-amber-900/30">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                            Device Uptime
                        </CardTitle>
                        <div class="p-2.5 rounded-xl bg-amber-500/10 dark:bg-amber-500/20">
                            <TrendingUp class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="text-3xl font-bold text-amber-900 dark:text-amber-50">
                            {{ metrics.devices.active }}
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Uptime Rate</span>
                                <span class="font-semibold text-amber-600 dark:text-amber-400">
                                    {{ deviceActivePercentage }}%
                                </span>
                            </div>
                            <div class="h-2 bg-amber-100 dark:bg-amber-950 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-amber-500 to-amber-600 rounded-full transition-all duration-500"
                                    :style="{ width: `${deviceActivePercentage}%` }"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bottom Section -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Subscription Breakdown -->
                <Card class="border-none shadow-lg">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-xl font-bold">Subscription Distribution</CardTitle>
                                <CardDescription class="mt-1.5">Active plans across all tenants</CardDescription>
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold">
                                {{ totalSubscriptions }} Total
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-5">
                            <div
                                v-for="plan in subscriptionPlans"
                                :key="plan.name"
                                class="group"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div :class="['h-3 w-3 rounded-full', plan.progressColor]" />
                                        <span class="text-sm font-semibold">{{ plan.label }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-muted-foreground">
                                            {{ getSubscriptionPercentage(metrics.subscription_breakdown[plan.name] || 0) }}%
                                        </span>
                                        <span class="text-base font-bold" :class="plan.color">
                                            {{ metrics.subscription_breakdown[plan.name] || 0 }}
                                        </span>
                                    </div>
                                </div>
                                <div class="h-2.5 bg-secondary rounded-full overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full transition-all duration-700 ease-out', plan.progressColor]"
                                        :style="{
                                            width: `${getSubscriptionPercentage(metrics.subscription_breakdown[plan.name] || 0)}%`
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Tenants -->
                <Card class="border-none shadow-lg">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-xl font-bold">Recent Tenants</CardTitle>
                                <CardDescription class="mt-1.5">Latest tenant registrations</CardDescription>
                            </div>
                            <Clock class="h-5 w-5 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-1">
                            <Link
                                v-for="tenant in metrics.recent_tenants"
                                :key="tenant.id"
                                :href="`/tenants/${tenant.id}`"
                                class="flex items-center justify-between p-3.5 rounded-lg hover:bg-accent/50 transition-all duration-200 group border border-transparent hover:border-border"
                            >
                                <div class="flex items-center gap-3.5 min-w-0 flex-1">
                                    <div
                                        :class="[
                                            'flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center font-bold text-sm',
                                            tenant.is_active
                                                ? 'bg-gradient-to-br from-green-500 to-emerald-600 text-white'
                                                : 'bg-gradient-to-br from-gray-400 to-gray-500 text-white'
                                        ]"
                                    >
                                        {{ tenant.company_name.substring(0, 2).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-sm truncate group-hover:text-primary transition-colors">
                                            {{ tenant.company_name }}
                                        </div>
                                        <div class="text-xs text-muted-foreground truncate">
                                            {{ tenant.subdomain }}.domain.com
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="text-right">
                                        <Badge
                                            :variant="tenant.is_active ? 'default' : 'secondary'"
                                            class="text-xs font-semibold"
                                        >
                                            {{ tenant.subscription_plan }}
                                        </Badge>
                                        <div class="text-xs text-muted-foreground mt-1">
                                            {{ formatDate(tenant.created_at) }}
                                        </div>
                                    </div>
                                    <div
                                        :class="[
                                            'h-2.5 w-2.5 rounded-full flex-shrink-0',
                                            tenant.is_active
                                                ? 'bg-green-500 animate-pulse'
                                                : 'bg-gray-400'
                                        ]"
                                        :title="tenant.is_active ? 'Active' : 'Inactive'"
                                    />
                                </div>
                            </Link>
                            <div
                                v-if="metrics.recent_tenants.length === 0"
                                class="text-center py-8 text-sm text-muted-foreground"
                            >
                                No recent tenants
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </SuperAdminLayout>
</template>
