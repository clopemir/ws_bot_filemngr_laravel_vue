<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Pagination.vue';
import { Pencil, Trash, CirclePlus, FolderOpen } from 'lucide-vue-next';
import { useClientForm } from '../../composables/useClients';

const { deleteClient } = useClientForm()


const props = defineProps({
    clients: {
        type: Object,
        required: true,
    },
});

const clients = computed(() => props.clients)

const search = ref(usePage().props.search ?? '');

const breadcrumbs = [
    {
        title: 'Clientes',
        href: '/clients',
    },
];


const submitSearch = useDebounceFn(() => {
    router.get('/clients', search.value ? { search: search.value } : {}, { preserveScroll: true, preserveState: true, replace: true });
}, 300);



</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex flex-row items-center justify-between mb-4">
                <Button as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link href="/clients/create"> <CirclePlus /> Crear</Link>
                </Button>
                <form @submit.prevent="submitSearch" class="flex items-center justify-end gap-3 mb-4">
                    <input
                        v-model="search"
                        @input="submitSearch"
                        type="text"
                        placeholder="Nombre, RFC o Agente..."
                        class="w-full max-w-xs rounded-md border px-3 py-2 text-sm text-gray-700 dark:text-white dark:bg-neutral-800 dark:border-neutral-700"
                    />
                    <Button type="submit" class="bg-blue-600 text-white hover:bg-blue-800">
                        Buscar
                    </Button>
                </form>
            </div>
            <div class="w-full overflow-x-auto">
                <div class="min-w-full rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Cliente</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">RFC</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Teléfono</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Correo</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Agente</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            <tr v-if="!clients.data.length"><td colspan="6" class="px-4 py-8 text-center text-sm text-muted-foreground">Sin resultados.</td></tr>
                            <tr v-for="cliente in clients.data" :key="cliente.id">
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ cliente.client_name }} {{ cliente.client_lname }} </td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 uppercase">{{ cliente.client_rfc }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ cliente.client_phone }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ cliente.client_mail }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ cliente.agent?.agent_name ?? 'Sin agente' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 flex justify-center gap-2">
                                    <Button v-if="cliente.folders?.length" as-child size="sm" variant="outline" title="Ver archivos">
                                        <Link :href="`/folder/${cliente.folders[0].path}`" :aria-label="`Ver archivos de ${cliente.client_name}`"> <FolderOpen /> </Link>
                                    </Button>

                                    <Button as-child size="sm" class="bg-blue-500 text-white hover:bg-blue-700">
                                        <Link :href="`/clients/${cliente.id}/edit`"> <Pencil /> </Link>
                                    </Button>

                                    <Button size="sm" class="bg-red-500 text-white hover:bg-red-700" @click="deleteClient(cliente.id)">
                                        <Trash />
                                    </Button>
                                    </td>

                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4"><Pagination :paginator="clients" /></div>
            </div>
        </div>
    </AppLayout>
</template>
