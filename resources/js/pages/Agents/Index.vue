<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Pagination.vue';
import { Pencil, Trash, CirclePlus } from 'lucide-vue-next';
import { useAgentForm } from '../../composables/useAgents';


const { deleteAgent } = useAgentForm()

const props = defineProps({
    agents: {
        type: Object,
        required: true,
    },
});


const breadcrumbs = [
    {
        title: 'Agentes',
        href: '/agents',
    },
];




</script>

<template>
    <Head title="Agentes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex">
                <Button as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link href="/agents/create"> <CirclePlus /> Crear</Link>
                </Button>
            </div>
            <div class="w-full overflow-x-auto">
                <div class="min-w-full rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Agente</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Teléfono</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Correo</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            <tr v-if="!agents.data.length"><td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">Sin resultados.</td></tr>
                            <tr v-for="agent in agents.data" :key="agent.id">
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ agent.agent_name }} {{ agent.agent_lname }} </td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ agent.agent_phone }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ agent.agent_mail }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 flex justify-center gap-2">
                                    <Button as-child size="sm" class="bg-blue-500 text-white hover:bg-blue-700">
                                        <Link :href="`/agents/${agent.id}/edit`"> <Pencil /> </Link>
                                    </Button>

                                    <Button size="sm" class="bg-red-500 text-white hover:bg-red-700" @click="deleteAgent(agent.id)">
                                        <Trash />
                                    </Button>
                                    </td>

                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4"><Pagination :paginator="agents" /></div>
            </div>
        </div>
    </AppLayout>
</template>
