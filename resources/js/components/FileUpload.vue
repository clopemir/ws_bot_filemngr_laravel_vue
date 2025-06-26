<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  folderId: {
    type: Number,
    required: true,
  },
});

const dragging = ref(false);
const files = ref([]);

const form = useForm({
  files: [],
  folder_id: props.folderId,
});

const fileInput = ref(null)

function handleDrop(event) {
  dragging.value = false;
  const droppedFiles = Array.from(event.dataTransfer.files);
  files.value = droppedFiles;
  form.files = droppedFiles;
  submit();
}

function submit() {
  form.post('/files', {
    onSuccess: () => {
      files.value = [];
      form.reset();
    },
    onError: () => {
      console.error('Error al subir archivos');
    },
  });
}
</script>

<template>
  <div>
    <div
      class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer bg-gray-50 dark:bg-gray-800 dark:border-gray-600"
      :class="dragging ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900' : ''"
      @click="() => fileInput.click()"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="handleDrop"
    >
      <p class="text-gray-700 dark:text-gray-200">
        Clic aquí para seleccionar los archivos (PDF, DOCX, EXCEL, IMG)
      </p>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept=".pdf, .xlsx, .docx, .jpg, .png"
        multiple
        @change="e => { form.files = Array.from(e.target.files); submit(); }"
      />
    </div>
  </div>
</template>

<style scoped>
input[type="file"] {
  display: none;
}
</style>
