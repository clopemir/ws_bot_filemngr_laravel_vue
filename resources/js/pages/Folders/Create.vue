<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
//import { useForm } from '@inertiajs/vue3'
import InputError from '@/components/InputError.vue';
import { toast } from "vue-sonner";


const props = defineProps({
    folder: {
        type: Object,
        required: true,
    },
})

const breadcrumbs = [{ title: 'Folders', href: '/folders' }, {title: props.folder.folder_name, href: `/folder/${props.folder.folder_name}`}, { title: 'Crear', href: '#' }];

const form = useForm({
    folder_name: '',
    parent_id: props.folder.id,
});

function submit() {
    form.post('/folders', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            setTimeout(() => {
              router.visit(`/folder/${props.folder.path}`, { preserveScroll: true });
            }, 2000);
          },
        onError: (errors) => {
            if (errors.folder) {
                toast.error(errors.folder);
            } else {
                toast.error('algo no salio bien')
            }
        }
    });
}
</script>

<template>

    <Head title="Nueva Carpeta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4 items-center">
            <div class="flex w-full max-w-2xl flex-col">

                <Card class="mt-3">
                    <CardHeader>
                        <CardTitle class="text-center">Crear Carpeta</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <form @submit.prevent="submit" class="space-y-6 items-center">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="grid w-full gap-2">
                                    <Label for="folder_name">Nombre de nueva carpeta</Label>
                                    <Input id="folder_name" v-model="form.folder_name" type="text" name="folder_name"
                                        placeholder="Nombre Carpeta"  />
                                    <InputError :message="form.errors.folder_name"/>
                                </div>

                            </div>

                            <div class="flex gap-4 justify-between">
                                <Button type="submit" class="bg-indigo-500 hover:bg-indigo-600" :disabled="form.processing">Crear</Button>
                                <Button as="a" :href="`/folder/${ folder.path }`" variant="outline">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>

                </Card>

            </div>
        </div>
    </AppLayout>

</template>