<script setup lang="ts">
import type { Ticket } from '../../../shared/types/api';

defineProps<{
    tickets: Ticket[];
}>();

function timeLabel(ticket: Ticket): string {
    const timestamp = ticket.finished_at ?? ticket.started_at ?? ticket.called_at;

    if (!timestamp) {
        return '';
    }

    return new Intl.DateTimeFormat('es-BO', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(timestamp));
}
</script>

<template>
    <section class="mt-6 rounded-[2rem] border border-brand-800 bg-brand-900 p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm uppercase tracking-[0.3em] text-accent-300">Últimos llamados</p>
            <p class="text-sm text-slate-400">Historial de la jornada</p>
        </div>

        <div v-if="tickets.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <article
                v-for="ticket in tickets"
                :key="ticket.id"
                class="rounded-2xl bg-brand-950/70 px-5 py-4"
            >
                <p class="text-3xl font-bold tracking-tight text-white">{{ ticket.label }}</p>
                <p class="mt-2 inline-flex rounded-xl bg-accent-300 px-3 py-1 text-base font-semibold text-brand-950">
                    {{ ticket.station_label ?? 'Sin ventanilla' }}
                </p>
                <p class="mt-2 text-xs uppercase tracking-[0.15em] text-slate-400">
                    {{ ticket.status_label }}
                    <span v-if="timeLabel(ticket)">· {{ timeLabel(ticket) }}</span>
                </p>
            </article>
        </div>

        <p v-else class="mt-4 text-lg text-slate-500">Aún no hay llamados en esta jornada.</p>
    </section>
</template>
