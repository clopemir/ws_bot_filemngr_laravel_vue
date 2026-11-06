<script setup>
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, UploadCloud } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    folderId: {
        type: Number,
        required: true,
    },
});

const MAX_BYTES = 20 * 1024 * 1024;

const dragging = ref(false);
const fileInput = ref(null);
const localError = ref('');

const form = useForm({
    files: [],
    folder_id: props.folderId,
});

const serverError = computed(() => Object.values(form.errors)[0] ?? '');

function upload(selected) {
    localError.value = '';
    form.clearErrors();

    const tooLarge = selected.find((file) => file.size > MAX_BYTES);

    if (tooLarge) {
        localError.value = `"${tooLarge.name}" supera el máximo de 20 MB. No se subió ningún archivo.`;
        return;
    }

    if (!selected.length || form.processing) {
        return;
    }

    form.files = selected;
    form.post('/files', {
        preserveScroll: true,
        onFinish: () => {
            form.files = [];
            // Permite volver a elegir el mismo archivo.
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function handleDrop(event) {
    dragging.value = false;
    upload(Array.from(event.dataTransfer.files));
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-colors hover:bg-accent/50 disabled:cursor-wait"
            :class="dragging ? 'border-primary bg-accent' : 'border-border'"
            :disabled="form.processing"
            @click="fileInput.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="handleDrop"
        >
            <LoaderCircle v-if="form.processing" class="size-6 animate-spin text-muted-foreground" />
            <UploadCloud v-else class="size-6 text-muted-foreground" />
            <span class="text-sm font-medium">
                {{ form.processing ? `Subiendo… ${form.progress?.percentage ?? 0}%` : 'Arrastra archivos aquí o haz clic para elegirlos' }}
            </span>
            <span class="text-xs text-muted-foreground">PDF, Word, Excel o imagen · Máximo 20 MB por archivo</span>
        </button>

        <div v-if="form.processing" class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="form.progress?.percentage ?? 0" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full bg-primary transition-all" :style="{ width: `${form.progress?.percentage ?? 0}%` }" />
        </div>

        <p v-if="localError || serverError" class="mt-2 text-sm text-destructive" role="alert">{{ localError || serverError }}</p>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            accept=".pdf,.xlsx,.docx,.jpg,.png"
            multiple
            @change="(event) => upload(Array.from(event.target.files))"
        />
    </div>
</template>
