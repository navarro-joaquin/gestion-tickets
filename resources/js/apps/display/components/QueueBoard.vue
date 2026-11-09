<script setup lang="ts">
import type { DisplaySnapshot, Ticket } from '../../../shared/types/api';

const props = defineProps<{
    sections: DisplaySnapshot['data'];
    compact?: boolean;
}>();

function calledTickets(tickets: Ticket[]): Ticket[] {
    return tickets.filter((ticket) => ticket.status === 'called');
}

function inServiceTickets(tickets: Ticket[]): Ticket[] {
    return tickets.filter((ticket) => ticket.status === 'in_service');
}

function lastCall(tickets: Ticket[]): Ticket | null {
    const called = calledTickets(tickets);

    return called.length > 0 ? called[called.length - 1] : null;
}

function previousCalls(tickets: Ticket[]): Ticket[] {
    const called = calledTickets(tickets);

    return called.length <= 1 ? [] : called.slice(0, -1).reverse();
}
</script>

<template>
    <div class="grid gap-6" :class="props.compact ? 'xl:grid-cols-1' : 'lg:grid-cols-3'">
        <article
            v-for="section in props.sections"
            :key="section.section.slug"
            class="flex flex-col rounded-[2rem] border border-brand-800 bg-brand-900"
            :class="props.compact ? 'p-6' : 'p-8'"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-accent-300">Sección</p>
                    <h2 class="mt-2 font-semibold" :class="props.compact ? 'text-2xl' : 'text-3xl'">
                        {{ section.section.name }}
                    </h2>
                </div>
                <span class="rounded-full bg-brand-800 px-5 py-2 text-slate-300" :class="props.compact ? 'text-base' : 'text-lg'">
                    {{ section.waiting_count }} en espera
                </span>
            </div>

            <div v-if="lastCall(section.calling)" class="mt-8 flex-1">
                <p class="text-lg uppercase tracking-[0.3em] text-emerald-400">Llamando</p>
                <p class="mt-4 font-black tracking-tight text-white" :class="props.compact ? 'text-6xl' : 'text-8xl'">
                    {{ lastCall(section.calling)?.label }}
                </p>
                <p class="mt-6 inline-flex rounded-2xl bg-accent-300 px-8 py-4 text-3xl font-semibold text-brand-950">
                    {{ lastCall(section.calling)?.station_label }}
                </p>
                <p class="mt-4 text-lg text-slate-400">
                    {{ lastCall(section.calling)?.status_label }}
                </p>

                <div v-if="previousCalls(section.calling).length > 0" class="mt-8 space-y-2 border-t border-brand-800 pt-6">
                    <p class="text-sm uppercase tracking-[0.2em] text-slate-500">Llamados anteriores</p>
                    <div
                        v-for="ticket in previousCalls(section.calling)"
                        :key="ticket.id"
                        class="flex items-center justify-between rounded-xl bg-brand-950/70 px-4 py-3"
                    >
                        <span class="text-2xl font-bold">{{ ticket.label }}</span>
                        <span class="text-lg text-slate-400">{{ ticket.station_label }}</span>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-8 flex flex-1 items-center justify-center rounded-3xl border border-dashed border-brand-800 p-10 text-center text-2xl text-slate-500"
            >
                Espere su llamado
            </div>

            <div
                v-if="inServiceTickets(section.calling).length > 0"
                class="mt-6 space-y-2 border-t border-brand-800 pt-6"
            >
                <p class="text-sm uppercase tracking-[0.2em] text-accent-200">En atención</p>
                <div
                    v-for="ticket in inServiceTickets(section.calling)"
                    :key="ticket.id"
                    class="flex items-center justify-between rounded-xl bg-brand-950/70 px-4 py-3"
                >
                    <span class="text-2xl font-bold">{{ ticket.label }}</span>
                    <span class="text-lg text-slate-400">{{ ticket.station_label }}</span>
                </div>
            </div>
        </article>
    </div>
</template>
