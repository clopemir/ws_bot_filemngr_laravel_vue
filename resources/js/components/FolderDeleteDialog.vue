<script setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    folder: { type: Object, default: null },
    fileCount: { type: Number, default: 0 },
    subfolderCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update:open']);

const form = useForm({ confirm_name: '' });

const hasContent = computed(() => props.fileCount > 0 || props.subfolderCount > 0);
const canDelete = computed(() => !hasContent.value || form.confirm_name === props.folder?.folder_name);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.clearErrors();
        }
    },
);

function submit() {
    form.delete(`/folders/${props.folder.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Eliminar “{{ folder?.folder_name }}”</DialogTitle>
                    <DialogDescription v-if="hasContent">
                        También se eliminarán definitivamente {{ fileCount }} archivo(s) y {{ subfolderCount }} subcarpeta(s). Esta acción no se puede deshacer.
                    </DialogDescription>
                    <DialogDescription v-else>La carpeta está vacía. Esta acción no se puede deshacer.</DialogDescription>
                </DialogHeader>

                <div v-if="hasContent" class="grid gap-2">
                    <Label for="confirm_name">Escribe <strong>{{ folder?.folder_name }}</strong> para confirmar</Label>
                    <Input id="confirm_name" v-model="form.confirm_name" autocomplete="off" />
                    <InputError :message="form.errors.confirm_name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancelar</Button>
                    <Button type="submit" variant="destructive" :disabled="form.processing || !canDelete">Eliminar</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
