<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { Building, File, Folder, LoaderCircle, Search, User } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface Result {
    type: 'Cliente' | 'Agente' | 'Carpeta' | 'Archivo';
    title: string;
    subtitle: string | null;
    href: string;
}

const icons = { Cliente: Building, Agente: User, Carpeta: Folder, Archivo: File };

const open = ref(false);
const query = ref('');
const results = ref<Result[]>([]);
const selected = ref(0);
const loading = ref(false);
const input = ref<HTMLInputElement | null>(null);
let controller: AbortController | null = null;

const hasQuery = computed(() => query.value.trim().length >= 2);

useEventListener(document, 'keydown', (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = true;
    }
});

const search = useDebounceFn(async () => {
    controller?.abort();

    if (!hasQuery.value) {
        results.value = [];
        loading.value = false;
        return;
    }

    controller = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(query.value.trim())}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        results.value = response.ok ? (await response.json()).results : [];
        selected.value = 0;
        loading.value = false;
    } catch (error) {
        // Una búsqueda cancelada deja el control a la que la reemplazó.
        if ((error as Error).name !== 'AbortError') {
            results.value = [];
            loading.value = false;
        }
    }
}, 200);

watch(query, search);

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        input.value?.focus();
    } else {
        query.value = '';
        results.value = [];
    }
});

function go(result: Result | undefined) {
    if (!result) return;
    open.value = false;
    router.visit(result.href);
}

function move(step: number) {
    if (results.value.length) {
        selected.value = (selected.value + step + results.value.length) % results.value.length;
    }
}
</script>

<template>
    <button
        type="button"
        class="flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm text-muted-foreground transition-colors hover:bg-accent"
        aria-label="Buscar en el panel"
        @click="open = true"
    >
        <Search class="size-4" />
        <span class="hidden sm:inline">Buscar…</span>
        <kbd class="hidden rounded border bg-muted px-1.5 text-xs md:inline">Ctrl K</kbd>
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="top-[15%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl">
            <DialogTitle class="sr-only">Buscar en el panel</DialogTitle>
            <DialogDescription class="sr-only">Busca clientes, agentes, carpetas y archivos.</DialogDescription>

            <div class="flex items-center gap-2 border-b px-4 pr-10">
                <LoaderCircle v-if="loading" class="size-4 shrink-0 animate-spin text-muted-foreground" />
                <Search v-else class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="input"
                    v-model="query"
                    type="text"
                    placeholder="Cliente, RFC, agente, carpeta o archivo…"
                    aria-label="Término de búsqueda"
                    class="h-12 w-full bg-transparent text-sm outline-none"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="go(results[selected])"
                />
            </div>

            <ul v-if="results.length" class="max-h-96 overflow-y-auto p-2">
                <li v-for="(result, index) in results" :key="`${result.type}-${result.href}-${result.title}`">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm"
                        :class="index === selected ? 'bg-accent' : ''"
                        @mouseenter="selected = index"
                        @click="go(result)"
                    >
                        <component :is="icons[result.type]" class="size-4 shrink-0 text-muted-foreground" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ result.title }}</span>
                            <span v-if="result.subtitle" class="block truncate text-xs text-muted-foreground">{{ result.subtitle }}</span>
                        </span>
                        <span class="text-xs text-muted-foreground">{{ result.type }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="p-6 text-center text-sm text-muted-foreground">
                {{ hasQuery && !loading ? 'Sin resultados.' : 'Escribe al menos 2 caracteres.' }}
            </p>
        </DialogContent>
    </Dialog>
</template>
