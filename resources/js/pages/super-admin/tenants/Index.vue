<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Building2 } from 'lucide-vue-next';

interface Tenant {
    id: string;
    company_name: string;
    subdomain: string;
    domain: string | null;
    subscription_plan: string;
    is_active: boolean;
    created_at: string;
    max_employees: number;
    max_devices: number;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    tenants: PaginatedData<Tenant>;
}>();

const getPlanBadgeColor = (plan: string) => {
    const colors: Record<string, string> = {
        trial: 'bg-gray-100 text-gray-800',
        basic: 'bg-blue-100 text-blue-800',
        professional: 'bg-purple-100 text-purple-800',
        enterprise: 'bg-orange-100 text-orange-800',
    };
    return colors[plan] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <SuperAdminLayout :breadcrumbs="[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Tenants', href: '/tenants' }
    ]">
        <Head title="Tenants" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Tenants</h1>
                    <p class="text-muted-foreground">Manage all system tenants</p>
                </div>
                <Button as-child>
                    <Link href="/tenants/create">
                        <Building2 class="mr-2 h-4 w-4" />
                        Create Tenant
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Tenants</CardTitle>
                    <CardDescription>{{ tenants?.total || 0 }} total tenants in the system</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Company</TableHead>
                                <TableHead>Subdomain</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Limits</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody v-if="tenants.data && tenants.data.length > 0">
                            <TableRow v-for="tenant in tenants.data" :key="tenant.id">
                                <TableCell class="font-medium">
                                    <div class="flex flex-col">
                                        <span>{{ tenant.company_name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ tenant.id }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <code class="text-xs bg-muted px-2 py-1 rounded">{{ tenant.subdomain }}</code>
                                </TableCell>
                                <TableCell>
                                    <span :class="['text-xs px-2 py-1 rounded-full font-medium', getPlanBadgeColor(tenant.subscription_plan)]">
                                        {{ tenant.subscription_plan }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <div :class="[
                                            'h-2 w-2 rounded-full',
                                            tenant.is_active ? 'bg-green-500' : 'bg-gray-400'
                                        ]" />
                                        <span class="text-sm">{{ tenant.is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="text-xs text-muted-foreground">
                                        <div>{{ tenant.max_employees }} employees</div>
                                        <div>{{ tenant.max_devices }} devices</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-xs text-muted-foreground">
                                        {{ new Date(tenant.created_at).toLocaleDateString() }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="`/tenants/${tenant.id}`">
                                                <Eye class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <TableBody v-else>
                            <TableRow>
                                <TableCell colspan="7" class="h-24 text-center">
                                    <div class="py-12 text-center">
                                        <Building2 class="mx-auto h-12 w-12 text-muted-foreground/50" />
                                        <h3 class="mt-4 text-lg font-semibold">No tenants found</h3>
                                        <p class="text-sm text-muted-foreground">Create your first tenant to get started.</p>
                                        <Button as-child class="mt-4">
                                            <Link href="/tenants/create">Create Tenant</Link>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </SuperAdminLayout>
</template>
