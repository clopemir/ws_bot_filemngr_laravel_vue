<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Pencil, Trash, CirclePlus, ArrowBigLeft } from 'lucide-vue-next';
import { useAgentForm } from '../../composables/useAgents';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import FileUpload from '@/components/FileUpload.vue';


const { deleteAgent } = useAgentForm()


const props = defineProps({
    folder: {
        type: Object,
        required: true,
    },
});

console.log(props.folder);



const breadcrumbs = computed(() => [
    {
        title: 'Folders',
        href: '/folders',
    },
    {
        title: props.folder.folder_name ?? 'Contenido',
        href: props.folder.path ?? '#',
    }
]);


function goToPage(url = null) {
    if (url) {
        router.visit(url, {preserveScroll: true})
    }
}



</script>

<template>
    <Head title="Carpetas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex">
                <Button v-if="!folder.parent_id" as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link href="/folders/create"> <CirclePlus /> Crear</Link>
                </Button>
                <Button v-else as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link :href="`/folders/${ folder.parent_id }`"> <ArrowBigLeft /> Volver</Link>
                </Button>
            </div>
            <div class="w-full overflow-x-auto grid md:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-6">

                <a v-for="item in folder.children" :key="item.id" :href="`/${ item.path }`" class="group flex flex-col bg-white border shadow-md rounded-xl hover:shadow-lg focus:outline-none focus:shadow-lg transition dark:bg-neutral-900 dark:border-neutral-800">
                        <div class="p-4 md:p-5">
                            <div class="flex justify-between items-center gap-x-3">
                                <div class="grow">
                                <h3 class="group-hover:text-blue-600 font-semibold text-gray-800 dark:group-hover:text-neutral-400 dark:text-neutral-200 uppercase">
                                    {{ item.folder_name }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-neutral-500">
                                    {{ item.length }} Sub-Carpetas
                                </p>
                                </div>
                                <div>
                                <svg class="shrink-0 size-5 text-gray-800 dark:text-neutral-200" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </div>
                            </div>
                        </div>
                    </a>

                    <div v-if="folder.parent_id" class="grid w-full max-w-sm items-center gap-3">
                        <Label for="file_upload" class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-200">Subir Archivos</Label>
                        <FileUpload :folder-id="folder.id" />
                    </div>

                    <div v-if="folder.files && folder.files.length" class="mt-6">
                        <h2 class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-200">Archivos en esta carpeta</h2>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-100 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Nombre</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                    <tr v-for="file in folder.files" :key="file.id">
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                            <a :href="file.url" target="_blank" class="text-blue-600 hover:underline">{{ file.original_name }}</a>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                                            <Button size="sm" class="bg-red-500 text-white hover:bg-red-700" @click="router.delete(`/files/${file.id}`)">
                                                <Trash />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


            </div>

        </div>
    </AppLayout>
</template>
