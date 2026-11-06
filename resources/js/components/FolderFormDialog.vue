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
    // Con carpeta se renombra; sin ella se crea dentro de parentId.
    folder: { type: Object, default: null },
    parentId: { type: Number, default: null },
    // Se renombra la carpeta que se está viendo: hay que ir a su nueva URL.
    openAfterRename: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

const form = useForm({ folder_name: '', parent_id: null, open: false });
const isRename = computed(() => Boolean(props.folder));

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.clearErrors();
            form.folder_name = props.folder?.folder_name ?? '';
            form.parent_id = props.parentId;
            form.open = props.openAfterRename;
        }
    },
);

function submit() {
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };

    if (isRename.value) {
        form.put(`/folders/${props.folder.id}`, options);
    } else {
        form.post('/folders', options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ isRename ? 'Renombrar carpeta' : 'Nueva carpeta' }}</DialogTitle>
                    <DialogDescription>
                        {{ isRename ? 'Los clientes verán los archivos en el bot con el nuevo nombre.' : 'El nombre será la categoría que verán los clientes en el bot.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="folder_name">Nombre</Label>
                    <Input id="folder_name" v-model="form.folder_name" maxlength="100" autocomplete="off" autofocus placeholder="Ej. Estados de cuenta" />
                    <InputError :message="form.errors.folder_name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing || !form.folder_name.trim()">
                        {{ isRename ? 'Guardar' : 'Crear carpeta' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
