<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';



const props = defineProps({
    folders: {
        type: Object,
        required: true,
    }
});

const search = ref(usePage().props.search ?? '');

const breadcrumbs = [
    {
        title: 'Folders',
        href: '/folders',
    },
];

function goToPage(url = null) {
    if (url) {
        router.visit(url, {preserveScroll: true})
    }
}

function submitSearch() {
  router.get('/folders', { search: search.value }, { preserveScroll: true, preserveState: true });
}



</script>

<template>
    <Head title="Carpetas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="">
                <!-- <Button as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link href="/folders/create"> <CirclePlus /> Crear</Link>
                </Button> -->
                <form @submit.prevent="submitSearch" class="flex items-center justify-end gap-3 mb-4">
                    <input
                        v-model="search"
                        @input="submitSearch"
                        type="text"
                        placeholder="Buscar carpeta..."
                        class="w-full max-w-xs rounded-md border px-3 py-2 text-sm text-gray-700 dark:text-white dark:bg-neutral-800 dark:border-neutral-700"
                    />
                    <Button type="submit" class="bg-blue-600 text-white hover:bg-blue-800">
                        Buscar
                    </Button>
                </form>
            </div>
            <div class="w-full overflow-x-auto grid md:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-6">
                <!-- <div class="min-w-full rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Carpeta</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Cliente</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            <tr v-for="folder in folders.data" :key="folder.id">
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ folder.folder_name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ folder.client.client_name }} {{ folder.client.client_lname }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 flex justify-center gap-2">
                                    <Button as-child size="sm" class="bg-blue-500 text-white hover:bg-blue-700">
                                        <Link :href="`/folders/${folder.id}/edit`"> <Pencil /> </Link>
                                    </Button>

                                    <Button size="sm" class="bg-red-500 text-white hover:bg-red-700" @click="deleteAgent(folder.id)">
                                        <Trash />
                                    </Button>
                                    </td>

                            </tr>
                        </tbody>
                    </table>
                </div> -->
                <a v-for="folder in folders.data" :key="folder.id" :href="`/folder/${folder.path}`" class="group flex flex-col bg-white border shadow-md rounded-xl hover:shadow-lg focus:outline-none focus:shadow-lg transition dark:bg-neutral-900 dark:border-neutral-800">
                        <div class="p-4 md:p-5">
                            <div class="flex justify-between items-center gap-x-3">
                                <div class="grow">
                                <h3 class="group-hover:text-blue-600 font-semibold text-gray-800 dark:group-hover:text-neutral-400 dark:text-neutral-200 uppercase">
                                    {{ folder.folder_name }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-neutral-500">
                                    {{ folder.children.length }} Sub-Carpetas
                                </p>
                                </div>
                                <div>
                                <svg class="shrink-0 size-5 text-gray-800 dark:text-neutral-200" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </div>
                            </div>
                        </div>
                    </a>


            </div>
             <!-- Paginación -->
            <div class="mt-4 flex flex-wrap items-center justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando {{ folders.from }} a {{ folders.to }} de {{ folders.total }} resultados
                </div>
                <div class="mt-2 flex space-x-1 md:mt-0">
                    <button
                        v-for="(link, index) in folders.links"
                        :key="index"
                        :disabled="!link.url"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                        class="rounded-md px-3 py-1 text-sm transition-all"
                        :class="[
                            link.active ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                            !link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-blue-500 hover:text-white',
                        ]"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
