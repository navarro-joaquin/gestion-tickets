<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../../shared/api/client';
import { getEcho } from '../../shared/echo';
import type { DisplaySnapshot, Ticket } from '../../shared/types/api';

const snapshot = ref<DisplaySnapshot | null>(null);
const error = ref('');
const clock = ref(new Date());

const echoClient = getEcho();
const subscribedChannels = new Set<string>();
let pollingTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const sections = computed(() => snapshot.value?.data ?? []);

const clockLabel = computed<string>(() =>
    new Intl.DateTimeFormat('es-BO', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(clock.value),
);

const dateLabel = computed<string>(() =>
    new Intl.DateTimeFormat('es-BO', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(clock.value),
);

onMounted(async () => {
    await loadSnapshot();
    subscribeToSections();

    pollingTimer = setInterval(() => {
        void loadSnapshot();
    }, 5000);

    clockTimer = setInterval(() => {
        clock.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    if (pollingTimer) {
        clearInterval(pollingTimer);
    }

    if (clockTimer) {
        clearInterval(clockTimer);
    }

    if (echoClient) {
        for (const channel of subscribedChannels) {
            echoClient.leave(channel);
        }
    }
});

async function loadSnapshot(): Promise<void> {
    try {
        const response = await api.snapshot();
        snapshot.value = response;
        error.value = '';
        subscribeToSections();
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'No se pudo actualizar la pantalla.';
    }
}

function subscribeToSections(): void {
    if (!echoClient || !snapshot.value) {
        return;
    }

    for (const section of snapshot.value.data) {
        const channelName = `sections.${section.section.slug}`;

        if (subscribedChannels.has(channelName)) {
            continue;
        }

        subscribedChannels.add(channelName);

        echoClient.channel(channelName).listen('.queue.changed', () => {
            void loadSnapshot();
        });
    }
}

function lastCall(tickets: Ticket[]): Ticket | null {
    return tickets.length > 0 ? tickets[tickets.length - 1] : null;
}

function previousCalls(tickets: Ticket[]): Ticket[] {
    return tickets.length <= 1 ? [] : tickets.slice(0, -1).reverse();
}
</script>

<template>
    <main class="flex min-h-screen flex-col overflow-hidden bg-slate-950 text-white">
        <header class="flex flex-wrap items-end justify-between gap-6 border-b border-slate-800 px-10 py-8">
            <div>
                <p class="text-lg uppercase tracking-[0.4em] text-sky-400">Turnos de atención</p>
                <h1 class="mt-2 text-4xl font-semibold">Pantalla de llamados</h1>
            </div>
            <div class="text-right">
                <p class="font-mono text-6xl font-bold tracking-tight text-slate-100">{{ clockLabel }}</p>
                <p class="mt-1 text-lg capitalize text-slate-400">{{ dateLabel }}</p>
            </div>
        </header>

        <section class="grid flex-1 gap-6 px-10 py-8 lg:grid-cols-3">
            <article
                v-for="section in sections"
                :key="section.section.slug"
                class="flex flex-col rounded-[2rem] border border-slate-800 bg-slate-900 p-8"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Sección</p>
                        <h2 class="mt-2 text-3xl font-semibold">{{ section.section.name }}</h2>
                    </div>
                    <span class="rounded-full bg-slate-800 px-5 py-2 text-lg text-slate-300">
                        {{ section.waiting_count }} en espera
                    </span>
                </div>

                <div v-if="lastCall(section.calling)" class="mt-10 flex-1">
                    <p class="text-lg uppercase tracking-[0.3em] text-emerald-400">Llamando</p>
                    <p class="mt-4 text-8xl font-black tracking-tight text-white">
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
                    class="mt-10 flex flex-1 items-center justify-center rounded-3xl border border-dashed border-slate-800 p-10 text-center text-2xl text-slate-500"
                >
                    Espere su llamado
                </div>
            </article>
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-800 px-10 py-5 text-sm text-slate-500">
            <p>
                {{ echoClient ? 'Actualización en tiempo real por WebSocket.' : 'Actualización automática cada 5 segundos.' }}
            </p>
            <p v-if="error" class="text-red-300">{{ error }}</p>
        </footer>
    </main>
</template>
