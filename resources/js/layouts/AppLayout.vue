<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { type BreadcrumbItemType } from '@/types';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css'


interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}
interface FlashMessages {
  success?: string;
  error?: string;
}


const page = usePage<{ flash: FlashMessages }>();


watch(
  () => page.props.flash,
  (flash) => {

    if (flash?.success) {
      toast.success(flash.success)
    }

    if (flash?.error) {
      toast.error(flash.error)
    }
  },
  { immediate: true } // También muestra mensajes en la carga inicial
)


withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
        <Toaster position="top-right" rich-colors/>
    </AppLayout>
</template>
