<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
//import { useForm } from '@inertiajs/vue3'
import { useAgentForm } from '@/composables/useAgents';
import InputError from '@/components/InputError.vue';

const breadcrumbs = [{ title: 'Agentes', href: '/agents' }, { title: 'Crear', href: '#' }];
const { createAgent, form } = useAgentForm()

</script>

<template>

    <Head title="Nuevo Agente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4 items-center">
            <div class="flex w-full max-w-2xl flex-col">

                <Card class="mt-3">
                    <CardHeader>
                        <CardTitle class="text-center">Crear Agente</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <form @submit.prevent="createAgent" class="space-y-6 items-center">
                            <div class="grid grid-cols-2 gap-6">
                                <div v-for="(label, key) in { agent_name: 'Nombre', agent_lname: 'Apellido', agent_phone: 'Telefono', agent_mail: 'Correo' }"
                                    :key="key" class="grid w-full gap-2">
                                    <Label :for="key">{{ label }}</Label>
                                    <Input :id="key" v-model="form[key]" :type="key === 'agent_mail' ? 'email' : 'text'"
                                        :placeholder="label"  />
                                    <InputError :message="form.errors[key]" />
                                </div>

                            </div>

                            <div class="flex gap-4 justify-between">
                                <Button type="submit" class="bg-indigo-500 hover:bg-indigo-600" :disabled="form.processing">Crear</Button>
                                <Button as="a" href="/agents" variant="outline">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>

                </Card>

            </div>
        </div>
    </AppLayout>

</template>