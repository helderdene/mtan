<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import employees from '@/routes/employees';
import departments from '@/routes/departments';
import devices from '@/routes/devices';
import shifts from '@/routes/shifts';
import attendance from '@/routes/attendance';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Users, Smartphone, Briefcase, Activity, Clock, Calendar } from 'lucide-vue-next';
import { urlIsActive } from '@/lib/utils';
import AppLogo from './AppLogo.vue';

const page = usePage();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Live Attendance',
        href: attendance.live().url,
        icon: Activity,
    },
];

const managementNavItems: NavItem[] = [
    {
        title: 'Employees',
        href: employees.index().url,
        icon: Users,
    },
    {
        title: 'Departments',
        href: departments.index().url,
        icon: Briefcase,
    },
    {
        title: 'Shifts',
        href: shifts.index().url,
        icon: Clock,
    },
    {
        title: 'Devices',
        href: devices.index().url,
        icon: Smartphone,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Main Section -->
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>Overview</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="urlIsActive(item.href, page.url)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Management Section -->
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>Management</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in managementNavItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="urlIsActive(item.href, page.url)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
