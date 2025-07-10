<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { type DateValue, getLocalTimeZone, today } from '@internationalized/date';
import {type Ref, ref} from 'vue';
import { Calendar } from '@/components/ui/calendar';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const value = ref(today(getLocalTimeZone())) as Ref<DateValue>

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">


        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">


            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-auto overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border flex items-center justify-center">
                    <Calendar v-model="value" :weekday-format="'short'" class="" />
                </div>
                <div class="relative aspect-auto overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-auto overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
