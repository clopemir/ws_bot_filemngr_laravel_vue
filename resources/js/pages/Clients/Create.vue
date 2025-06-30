<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { useClientForm } from '@/composables/useClients';

const { form, createClient } = useClientForm()

const breadcrumbs = [{ title: 'Clientes', href: '/clients' }, { title: 'Crear', href: '#' }];

//Formulario
const props = defineProps({
    agents: {
        type: Array,
        required: true
    }
})


</script>

<template>

    <Head title="Nuevo Cliente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4 items-center">
            <div class="flex w-full max-w-2xl flex-col">

                <Card class="mt-3">
                    <CardHeader>
                        <CardTitle class="text-center">Crear Cliente</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <form @submit.prevent="createClient" class="space-y-6 items-center">
                            <div class="grid grid-cols-2 gap-6">
                                <div v-for="(label, key) in { client_name: 'Nombre | Empresa', client_lname: 'Apellido | Alias', client_rfc: 'RFC', client_phone: 'Telefono', client_mail: 'Correo' }"
                                    :key="key" class="grid w-full gap-2">
                                    <Label :for="key">{{ label }}</Label>
                                    <Input :id="key" v-model="form[key]" :type="key === 'client_mail' ? 'email' : 'text'"
                                        :placeholder="label" :required="key" />
                                    <InputError :message="form.errors[key]" />
                                </div>
                                <div class="grid w-full gap-2 mt-5 items-center">
                                    <Select v-model="form.agent_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona un Agente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectLabel>Agentes Disponibles</SelectLabel>

                                                <SelectItem v-for="agent in agents" :key="agent.id" :value="agent.id">{{
                                                    agent.agent_name }}</SelectItem>

                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="flex gap-4 justify-between">
                                <Button type="submit" class="bg-indigo-500 hover:bg-indigo-600" :disabled="form.processing">Crear</Button>
                                <Button as="a" href="/clients" variant="outline">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>

                </Card>

            </div>
        </div>
    </AppLayout>

</template>