<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    paginator: {
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

// Las etiquetas de Laravel traen entidades HTML y vienen en inglés según el idioma del paquete.
function label(raw: string): string {
    if (raw.includes('Previous') || raw.includes('Anterior') || raw.includes('laquo')) return '‹';
    if (raw.includes('Next') || raw.includes('Siguiente') || raw.includes('raquo')) return '›';
    return raw;
}
</script>

<template>
    <nav v-if="paginator.total > 0" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <p class="text-muted-foreground">{{ paginator.from }}–{{ paginator.to }} de {{ paginator.total }}</p>
        <div class="flex gap-1">
            <template v-for="link in paginator.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md border px-3 py-1 transition-colors hover:bg-accent"
                    :class="link.active ? 'border-primary bg-primary text-primary-foreground hover:bg-primary' : ''"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ label(link.label) }}
                </Link>
                <span v-else class="rounded-md border px-3 py-1 opacity-40">{{ label(link.label) }}</span>
            </template>
        </div>
    </nav>
</template>
