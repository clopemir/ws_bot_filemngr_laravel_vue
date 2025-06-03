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

const breadcrumbs = [{ title: 'Clientes', href: '/clients' }, { title: 'Editar', href: '#' }];

//Formulario
const props = defineProps({
    agents: {
        type: Array,
        required: true
    },
    client: {
        type: Object,
        required: true
    }
})

const client = props.client;

const {form, updateClient} = useClientForm(props.client)


</script>

<template>

    <Head title="Editar Cliente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 items-center">
            <div class="flex w-full max-w-2xl flex-col">

                <Card class="mt-3">
                    <CardHeader>
                        <CardTitle class="text-center">Editar Cliente</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <form @submit.prevent="updateClient(client.id)" class="space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div v-for="(label, key) in { client_name: 'Nombre', client_lname: 'Apellido', client_rfc: 'RFC', client_phone: 'Telefono', client_mail: 'Correo' }"
                                    :key="key" class="grid w-full gap-2">
                                    <Label :for="key">{{ label }}</Label>
                                    <Input :id="key" v-model="form[key]"
                                        :type="key === 'client_mail' ? 'email' : 'text'"
                                        :disabled="['client_rfc', 'client_phone', 'client_mail'].includes(key)"
                                        :class="['client_mail', 'client_rfc', 'client_phone'].includes(key) ? 'bg-gray-100 cursor-not-allowed' : ''"
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
                                <div class="flex items-center gap-3">
                                    <Label for="agent_status">Activo</Label>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="form.client_status"
                                        @click="form.client_status = !form.client_status"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                        :class="form.client_status ? 'bg-green-500' : 'bg-gray-300'"
                                    >
                                        <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200"
                                        :class="form.client_status ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-4 justify-between">
                                <Button type="submit" class="bg-indigo-500 hover:bg-indigo-600" :disabled="form.processing">Guardar Cambios</Button>
                                <Button as="a" href="/clients" variant="outline">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>

                </Card>

            </div>
        </div>
    </AppLayout>

</template>