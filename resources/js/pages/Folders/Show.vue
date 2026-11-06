<script setup>
import FileUpload from '@/components/FileUpload.vue';
import FolderDeleteDialog from '@/components/FolderDeleteDialog.vue';
import FolderFormDialog from '@/components/FolderFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatBytes, formatDate } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { CirclePlus, Download, FileText, Folder, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    folder: { type: Object, required: true },
    ancestors: { type: Array, default: () => [] },
    clientInfo: { type: Object, default: null },
});

const isRoot = computed(() => !props.folder.parent_id);
const clientName = computed(() => (props.clientInfo ? `${props.clientInfo.client_name} ${props.clientInfo.client_lname ?? ''}`.trim() : null));
const title = computed(() => (isRoot.value && clientName.value ? clientName.value : props.folder.folder_name));

// La carpeta principal se muestra con el nombre del cliente, no con su RFC.
const breadcrumbs = computed(() => [
    { title: 'Archivos', href: '/folders' },
    ...props.ancestors.map((item) => ({
        title: !item.parent_id && clientName.value && item.path === props.ancestors[0].path ? clientName.value : item.folder_name,
        href: `/folder/${item.path}`,
    })),
    { title: title.value, href: `/folder/${props.folder.path}` },
]);

const createOpen = ref(false);
const renameOpen = ref(false);
const deleteOpen = ref(false);
const target = ref(null);
const fileToDelete = ref(null);

function openRename(folder) {
    target.value = folder;
    renameOpen.value = true;
}

function openDelete(folder) {
    target.value = folder;
    deleteOpen.value = true;
}

const isCurrentTarget = computed(() => target.value?.id === props.folder.id);
const targetFileCount = computed(() => (isCurrentTarget.value ? props.folder.files.length : (target.value?.files_count ?? 0)));
const targetSubfolderCount = computed(() => (isCurrentTarget.value ? props.folder.children.length : (target.value?.children_count ?? 0)));

function deleteFile() {
    router.delete(`/files/${fileToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => (fileToDelete.value = null),
    });
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold">{{ title }}</h1>
                    <p v-if="clientInfo" class="text-sm text-muted-foreground">
                        <span v-if="!isRoot">{{ clientName }} · </span>
                        <span class="uppercase">{{ clientInfo.client_rfc }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="isRoot" size="sm" @click="createOpen = true"><CirclePlus /> Nueva carpeta</Button>
                    <template v-else>
                        <Button size="sm" variant="outline" @click="openRename(folder)"><Pencil /> Renombrar</Button>
                        <Button size="sm" variant="outline" class="text-destructive hover:text-destructive" @click="openDelete(folder)"><Trash2 /> Eliminar</Button>
                    </template>
                </div>
            </div>

            <!-- Carpeta principal: carpetas del cliente -->
            <section v-if="isRoot || folder.children.length" aria-label="Carpetas">
                <div v-if="folder.children.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="item in folder.children" :key="item.id" class="flex items-center rounded-xl border bg-card transition-colors hover:bg-accent/50">
                        <Link :href="`/folder/${item.path}`" class="flex min-w-0 flex-1 items-center gap-3 p-4">
                            <Folder class="size-5 shrink-0 text-primary" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ item.folder_name }}</span>
                                <span class="block text-xs text-muted-foreground">{{ item.files_count }} archivo(s)</span>
                            </span>
                        </Link>
                        <div class="flex shrink-0 pr-2">
                            <Button size="icon" variant="ghost" :aria-label="`Renombrar ${item.folder_name}`" title="Renombrar" @click="openRename(item)"><Pencil /></Button>
                            <Button size="icon" variant="ghost" class="text-destructive hover:text-destructive" :aria-label="`Eliminar ${item.folder_name}`" title="Eliminar" @click="openDelete(item)"><Trash2 /></Button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed p-8 text-center">
                    <p class="text-sm text-muted-foreground">Este cliente aún no tiene carpetas.</p>
                    <Button class="mt-3" size="sm" @click="createOpen = true"><CirclePlus /> Crear la primera</Button>
                </div>
            </section>

            <!-- Subcarpeta: archivos -->
            <template v-if="!isRoot">
                <FileUpload :key="folder.id" :folder-id="folder.id" />

                <section class="rounded-xl border bg-card" aria-label="Archivos">
                    <h2 class="border-b p-4 text-sm font-semibold">Archivos ({{ folder.files.length }})</h2>
                    <ul v-if="folder.files.length" class="divide-y">
                        <li v-for="file in folder.files" :key="file.id" class="flex items-center gap-3 p-3 px-4 text-sm">
                            <FileText class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1">
                                <a :href="`/files/${file.id}`" class="block truncate font-medium hover:underline">{{ file.original_file_name }}</a>
                                <span class="text-xs text-muted-foreground">{{ formatBytes(file.file_size) }} · {{ formatDate(file.created_at) }}</span>
                            </span>
                            <Button as-child size="icon" variant="ghost" title="Descargar">
                                <a :href="`/files/${file.id}`" :aria-label="`Descargar ${file.original_file_name}`"><Download /></a>
                            </Button>
                            <Button size="icon" variant="ghost" class="text-destructive hover:text-destructive" title="Eliminar" :aria-label="`Eliminar ${file.original_file_name}`" @click="fileToDelete = file"><Trash2 /></Button>
                        </li>
                    </ul>
                    <p v-else class="p-6 text-center text-sm text-muted-foreground">Todavía no hay archivos en esta carpeta.</p>
                </section>
            </template>
        </div>

        <FolderFormDialog v-model:open="createOpen" :parent-id="folder.id" />
        <FolderFormDialog v-model:open="renameOpen" :folder="target" :open-after-rename="isCurrentTarget" />
        <FolderDeleteDialog v-model:open="deleteOpen" :folder="target" :file-count="targetFileCount" :subfolder-count="targetSubfolderCount" />

        <Dialog :open="Boolean(fileToDelete)" @update:open="(value) => { if (!value) fileToDelete = null; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Eliminar archivo</DialogTitle>
                    <DialogDescription>“{{ fileToDelete?.original_file_name }}” se eliminará definitivamente y el cliente ya no podrá descargarlo.</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="fileToDelete = null">Cancelar</Button>
                    <Button variant="destructive" @click="deleteFile">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
