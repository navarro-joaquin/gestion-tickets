<script setup lang="ts">
import type { DisplaySnapshot, Ticket } from '../../../shared/types/api';

const props = defineProps<{
    sections: DisplaySnapshot['data'];
    compact?: boolean;
}>();

function lastCall(tickets: Ticket[]): Ticket | null {
    return tickets.length > 0 ? tickets[tickets.length - 1] : null;
}

function previousCalls(tickets: Ticket[]): Ticket[] {
    return tickets.length <= 1 ? [] : tickets.slice(0, -1).reverse();
}
</script>

<template>
    <div class="grid gap-6" :class="props.compact ? 'xl:grid-cols-1' : 'lg:grid-cols-3'">
        <article
            v-for="section in props.sections"
            :key="section.section.slug"
            class="flex flex-col rounded-[2rem] border border-slate-800 bg-slate-900"
            :class="props.compact ? 'p-6' : 'p-8'"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Sección</p>
                    <h2 class="mt-2 font-semibold" :class="props.compact ? 'text-2xl' : 'text-3xl'">
                        {{ section.section.name }}
                    </h2>
                </div>
                <span class="rounded-full bg-slate-800 px-5 py-2 text-slate-300" :class="props.compact ? 'text-base' : 'text-lg'">
                    {{ section.waiting_count }} en espera
                </span>
            </div>

            <div v-if="lastCall(section.calling)" class="mt-8 flex-1">
                <p class="text-lg uppercase tracking-[0.3em] text-emerald-400">Llamando</p>
                <p class="mt-4 font-black tracking-tight text-white" :class="props.compact ? 'text-6xl' : 'text-8xl'">
                    {{ lastCall(section.calling)?.label }}
                </p>
                <p class="mt-6 inline-flex rounded-2xl bg-sky-500 px-8 py-4 text-3xl font-semibold text-slate-950">
                    {{ lastCall(section.calling)?.station_label }}
                </p>
                <p class="mt-4 text-lg text-slate-400">
                    {{ lastCall(section.calling)?.status_label }}
                </p>

                <div v-if="previousCalls(section.calling).length > 0" class="mt-8 space-y-2 border-t border-slate-800 pt-6">
                    <p class="text-sm uppercase tracking-[0.2em] text-slate-500">Llamados anteriores</p>
                    <div
                        v-for="ticket in previousCalls(section.calling)"
                        :key="ticket.id"
                        class="flex items-center justify-between rounded-xl bg-slate-950/60 px-4 py-3"
                    >
                        <span class="text-2xl font-bold">{{ ticket.label }}</span>
                        <span class="text-lg text-slate-400">{{ ticket.station_label }}</span>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-8 flex flex-1 items-center justify-center rounded-3xl border border-dashed border-slate-800 p-10 text-center text-2xl text-slate-500"
            >
                Espere su llamado
            </div>
        </article>
    </div>
</template>
