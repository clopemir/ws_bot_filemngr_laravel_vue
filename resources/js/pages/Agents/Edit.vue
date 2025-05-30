<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { useAgentForm } from '@/composables/useAgents';

const breadcrumbs = [{ title: 'Agentes', href: '/agents' }, { title: 'Editar', href: '#' }];


//Formulario
const props = defineProps({
    agent: {
        type: Object,
        required: true
    }
})

const agent = props.agent;

const { form, updateAgent } = useAgentForm(props.agent)

</script>

<template>

    <Head title="Editar Agente" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 items-center">
            <div class="flex w-full max-w-2xl flex-col">

                <Card class="mt-3">
                    <CardHeader>
                        <CardTitle class="text-center">Editar Agente</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <form @submit.prevent="updateAgent(agent.id)" class="space-y-6 items-center">
                            <div class="grid grid-cols-2 gap-6">
                                <div v-for="(label, key) in { agent_name: 'Nombre', agent_lname: 'Apellido', agent_phone: 'Telefono', agent_mail: 'Correo' }"
                                    :key="key" class="grid w-full gap-2">
                                    <Label :for="key">{{ label }}</Label>
                                    <Input :id="key" v-model="form[key]" :type="key === 'agent_mail' ? 'email' : 'text'"
                                        :disabled="['agent_phone', 'agent_mail'].includes(key)"
                                        :class="['agent_mail', 'agent_phone'].includes(key) ? 'bg-gray-100 cursor-not-allowed' : ''"
                                        :placeholder="label"  />
                                    <InputError :message="form.errors[key]" />
                                </div>
                                <div class="flex items-center gap-3">
                                    <Label for="agent_status">Activo</Label>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="form.agent_status"
                                        @click="form.agent_status = !form.agent_status"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                        :class="form.agent_status ? 'bg-green-500' : 'bg-gray-300'"
                                    >
                                        <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200"
                                        :class="form.agent_status ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                            
                            </div>

                            <div class="flex gap-4 justify-between">
                                <Button type="submit" class="bg-indigo-500 hover:bg-indigo-600" :disabled="form.processing">Editar</Button>
                                <Button as="a" href="/agents" variant="outline">Cancelar</Button>
                            </div>
                        </form>

                    </CardContent>

                </Card>

            </div>
        </div>
    </AppLayout>

</template>