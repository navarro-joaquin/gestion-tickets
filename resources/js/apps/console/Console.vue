<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../../shared/api/client';
import { getEcho } from '../../shared/echo';
import type { ConsoleQueue, Section, Ticket } from '../../shared/types/api';

const sections = ref<Section[]>([]);
const sectionSlug = ref('');
const station = ref('Ventanilla 1');
const queue = ref<ConsoleQueue | null>(null);
const message = ref('');
const error = ref('');
const busy = ref(false);

const echoClient = getEcho();
let channelName: string | null = null;
let pollingTimer: ReturnType<typeof setInterval> | undefined;

const activeTicket = computed<Ticket | null>(() => queue.value?.active_ticket ?? null);
const waitingCount = computed<number>(() => queue.value?.waiting_count ?? 0);

const canRecall = computed<boolean>(
    () => activeTicket.value?.status === 'called' && activeTicket.value.call_count < 2,
);
const canStart = computed<boolean>(() => activeTicket.value?.status === 'called');
const canFinish = computed<boolean>(() => activeTicket.value?.status === 'in_service');
const canCallNext = computed<boolean>(() => activeTicket.value === null);

onMounted(async () => {
    const storedStation = localStorage.getItem('console-station');

    if (storedStation) {
        station.value = storedStation;
    }

    await loadSections();

    pollingTimer = setInterval(() => {
        void loadQueue();
    }, 5000);
});

onBeforeUnmount(() => {
    if (pollingTimer) {
        clearInterval(pollingTimer);
    }

    if (echoClient && channelName) {
        echoClient.leave(channelName);
    }
});

watch(sectionSlug, () => {
    subscribe();
    void loadQueue();
});

watch(station, () => {
    localStorage.setItem('console-station', station.value);
    void loadQueue();
});

async function loadSections(): Promise<void> {
    try {
        const response = await api.sections();
        sections.value = response.data;

        if (sections.value.length > 0) {
            sectionSlug.value = sections.value[0].slug;
        }
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadQueue(): Promise<void> {
    if (!sectionSlug.value) {
        return;
    }

    try {
        const response = await api.queue(sectionSlug.value, station.value);
        queue.value = response.data;
        error.value = '';
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function subscribe(): void {
    if (!echoClient) {
        return;
    }

    if (channelName) {
        echoClient.leave(channelName);
        channelName = null;
    }

    if (!sectionSlug.value) {
        return;
    }

    channelName = `sections.${sectionSlug.value}`;

    echoClient.channel(channelName).listen('.queue.changed', () => {
        void loadQueue();
    });
}

async function callNext(): Promise<void> {
    if (busy.value || !sectionSlug.value) {
        return;
    }

    busy.value = true;

    try {
        const response = await api.next(sectionSlug.value, station.value);
        message.value = response.message;
        await loadQueue();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function runAction(action: 'recall' | 'start' | 'finish'): Promise<void> {
    if (busy.value || !activeTicket.value) {
        return;
    }

    busy.value = true;

    try {
        const response = await api[action](activeTicket.value.id);
        message.value = `Ticket ${response.data.label}: ${response.data.status_label}.`;
        await loadQueue();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-6 py-8 text-white">
        <header class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-6 border-b border-slate-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Gestión de tickets</p>
                <h1 class="text-3xl font-semibold">Consola de operador</h1>
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-sm text-slate-400">
                    Sección
                    <select
                        v-model="sectionSlug"
                        class="min-w-56 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100"
                    >
                        <option v-for="section in sections" :key="section.slug" :value="section.slug">
                            {{ section.name }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-sm text-slate-400">
                    Ventanilla
                    <input
                        v-model="station"
                        type="text"
                        maxlength="50"
                        class="w-48 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100"
                    >
                </label>
            </div>
        </header>

        <section class="mx-auto mt-8 grid max-w-6xl gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Ticket activo</h2>
                    <span class="rounded-full bg-slate-800 px-4 py-1 text-sm text-slate-300">
                        {{ waitingCount }} en espera
                    </span>
                </div>

                <div v-if="activeTicket" class="mt-8">
                    <p class="text-7xl font-black tracking-tight">{{ activeTicket.label }}</p>
                    <p class="mt-3 text-xl text-slate-300">{{ activeTicket.category?.name }}</p>
                    <div class="mt-6 flex flex-wrap gap-3 text-sm">
                        <span class="rounded-full bg-sky-500/15 px-4 py-1 text-sky-300">
                            {{ activeTicket.status_label }}
                        </span>
                        <span class="rounded-full bg-slate-800 px-4 py-1 text-slate-300">
                            {{ activeTicket.call_count }} llamado(s)
                        </span>
                        <span class="rounded-full bg-slate-800 px-4 py-1 text-slate-300">
                            {{ activeTicket.station_label }}
                        </span>
                    </div>
                </div>

                <div v-else class="mt-10 rounded-2xl border border-dashed border-slate-700 p-10 text-center text-slate-400">
                    No hay un ticket activo en esta ventanilla.
                </div>

                <div class="mt-10 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !canCallNext"
                        @click="callNext"
                    >
                        Llamar siguiente
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-200 transition hover:border-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !canRecall"
                        @click="runAction('recall')"
                    >
                        Rellamar
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-200 transition hover:border-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !canStart"
                        @click="runAction('start')"
                    >
                        Iniciar atención
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-200 transition hover:border-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !canFinish"
                        @click="runAction('finish')"
                    >
                        Finalizar
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Estado de la conexión</h2>
                    <p class="mt-3 text-sm text-slate-400">
                        {{ echoClient ? 'Reverb configurado: los cambios se reciben en tiempo real.' : 'Sin Reverb: actualización cada 5 segundos.' }}
                    </p>
                </div>

                <div v-if="message" class="rounded-3xl border border-sky-500/40 bg-sky-500/10 p-6 text-sky-200">
                    {{ message }}
                </div>

                <div v-if="error" class="rounded-3xl border border-red-500/40 bg-red-500/10 p-6 text-red-200">
                    {{ error }}
                </div>
            </aside>
        </section>
    </main>
</template>
