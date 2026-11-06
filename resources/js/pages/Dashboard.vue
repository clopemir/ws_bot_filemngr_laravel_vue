<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatBytes, formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Bot, Building, FileText, FolderOpen, HardDrive, UserPlus, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    stats: {
        clients: number;
        activeClients: number;
        agents: number;
        files: number;
        filesThisWeek: number;
        storageBytes: number;
        clientsWithoutFiles: number;
    };
    recentFiles: {
        id: number;
        original_file_name: string;
        category: string | null;
        created_at: string;
        folder: { path: string; client: { client_name: string; client_lname: string | null } | null } | null;
    }[];
    aiUsage: { name: string; configured: boolean; used: number; limit: number; paused: boolean }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

const cards = computed(() => [
    { title: 'Clientes', value: props.stats.clients, detail: `${props.stats.activeClients} activos`, href: '/clients', icon: Building },
    { title: 'Agentes activos', value: props.stats.agents, detail: 'Con acceso por WhatsApp', href: '/agents', icon: Users },
    { title: 'Archivos', value: props.stats.files, detail: `${props.stats.filesThisWeek} en los últimos 7 días`, href: '/folders', icon: FileText },
    { title: 'Almacenamiento', value: formatBytes(props.stats.storageBytes), detail: 'Documentos de clientes', href: '/folders', icon: HardDrive },
]);

const providerNames: Record<string, string> = { gemini: 'Gemini', openrouter: 'OpenRouter' };

function usagePercent(provider: { used: number; limit: number }): number {
    return provider.limit > 0 ? Math.min(100, Math.round((provider.used / provider.limit) * 100)) : 100;
}

function providerStatus(provider: { configured: boolean; used: number; limit: number; paused: boolean }): string {
    if (!provider.configured) return 'Sin configurar';
    if (provider.paused) return 'En pausa';
    if (provider.limit > 0 && provider.used >= provider.limit) return 'Tope alcanzado';
    return 'Disponible';
}
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Panel de control</h1>
                <div class="flex flex-wrap gap-2">
                    <Button as-child size="sm">
                        <Link href="/clients/create"><UserPlus /> Nuevo cliente</Link>
                    </Button>
                    <Button as-child size="sm" variant="outline">
                        <Link href="/folders"><FolderOpen /> Ver archivos</Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in cards"
                    :key="card.title"
                    :href="card.href"
                    class="rounded-xl border bg-card p-4 transition-colors hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        {{ card.title }}
                        <component :is="card.icon" class="size-4" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ card.detail }}</p>
                </Link>
            </div>

            <Link
                v-if="stats.clientsWithoutFiles > 0"
                href="/folders"
                class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 transition-colors hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950"
            >
                <AlertTriangle class="size-4 shrink-0" />
                {{ stats.clientsWithoutFiles }} cliente(s) todavía sin documentos: no encontrarán nada en el bot.
            </Link>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border bg-card lg:col-span-2">
                    <h2 class="border-b p-4 text-sm font-semibold">Últimos archivos</h2>
                    <ul v-if="recentFiles.length" class="divide-y">
                        <li v-for="file in recentFiles" :key="file.id">
                            <Link :href="file.folder ? `/folder/${file.folder.path}` : '/folders'" class="flex items-center gap-3 p-3 px-4 text-sm transition-colors hover:bg-accent/50">
                                <FileText class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ file.original_file_name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">
                                        {{ file.folder?.client?.client_name }} {{ file.folder?.client?.client_lname }} · {{ file.category }}
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ formatDate(file.created_at) }}</span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="p-6 text-center text-sm text-muted-foreground">Aún no se han subido archivos.</p>
                </section>

                <section class="rounded-xl border bg-card">
                    <h2 class="flex items-center gap-2 border-b p-4 text-sm font-semibold"><Bot class="size-4" /> Uso de IA hoy</h2>
                    <ul class="space-y-4 p-4">
                        <li v-for="provider in aiUsage" :key="provider.name" class="text-sm">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium">{{ providerNames[provider.name] ?? provider.name }}</span>
                                <span class="text-xs text-muted-foreground">{{ providerStatus(provider) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full" :class="usagePercent(provider) >= 90 ? 'bg-destructive' : 'bg-primary'" :style="{ width: `${usagePercent(provider)}%` }" />
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground tabular-nums">{{ provider.used }} de {{ provider.limit }} llamadas</p>
                        </li>
                        <li v-if="!aiUsage.length" class="text-sm text-muted-foreground">No hay proveedores de IA activos.</li>
                    </ul>
                    <p class="border-t p-4 text-xs text-muted-foreground">Las solicitudes claras de documentos se resuelven sin gastar llamadas.</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
