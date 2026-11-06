<script setup>
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ChevronRight, Folder, Search } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    folders: { type: Object, required: true },
    search: { type: String, default: '' },
});

const term = ref(props.search ?? '');

const breadcrumbs = [{ title: 'Archivos', href: '/folders' }];

const submitSearch = useDebounceFn(() => {
    router.get('/folders', term.value ? { search: term.value } : {}, { preserveState: true, preserveScroll: true, replace: true });
}, 300);
</script>

<template>
    <Head title="Archivos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Archivos por cliente</h1>
                <div class="relative w-full sm:w-72">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="term"
                        type="search"
                        placeholder="Nombre o RFC…"
                        aria-label="Buscar cliente"
                        class="h-9 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @input="submitSearch"
                    />
                </div>
            </div>

            <div v-if="folders.data.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="folder in folders.data"
                    :key="folder.id"
                    :href="`/folder/${folder.path}`"
                    class="group flex items-center gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Folder class="size-5 shrink-0 text-primary" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">
                            {{ folder.client ? `${folder.client.client_name} ${folder.client.client_lname ?? ''}` : folder.folder_name }}
                        </span>
                        <span class="block truncate text-xs text-muted-foreground">
                            <span class="uppercase">{{ folder.folder_name }}</span>
                            · {{ folder.children_count }} carpeta(s) · {{ folder.client?.files_count ?? 0 }} archivo(s)
                        </span>
                    </span>
                    <ChevronRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                </Link>
            </div>
            <p v-else class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {{ term ? `Sin resultados para “${term}”.` : 'Todavía no hay clientes con carpetas.' }}
            </p>

            <Pagination :paginator="folders" />
        </div>
    </AppLayout>
</template>
