<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../../shared/api/client';
import { TicketAnnouncer } from '../../shared/audio/announcer';
import { getEcho } from '../../shared/echo';
import type { DisplaySnapshot, MediaAsset, MediaItem, Ticket } from '../../shared/types/api';
import CallHistory from './components/CallHistory.vue';
import MediaStage from './components/MediaStage.vue';
import QueueBoard from './components/QueueBoard.vue';

const AUDIO_STORAGE_KEY = 'tickets.display.audio';
const logoUrl = '/images/cessa-logo.jpg';

const displayToken = new URLSearchParams(window.location.search).get('pantalla') ?? undefined;

const snapshot = ref<DisplaySnapshot | null>(null);
const error = ref('');
const clock = ref(new Date());

const announcer = new TicketAnnouncer();
const audioEnabled = ref(false);
const audioError = ref('');
const speechSupported = announcer.speechSupported;

const echoClient = getEcho();
const subscribedChannels = new Set<string>();
const announcedCalls = new Map<number, string>();
let snapshotLoaded = false;
let pollingTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const sections = computed(() => snapshot.value?.data ?? []);
const recentTickets = computed<Ticket[]>(() => snapshot.value?.recent ?? []);
const display = computed(() => snapshot.value?.display ?? null);
const layout = computed(() => display.value?.layout ?? 'queue');

const mediaItems = computed<MediaItem[]>(() =>
    (snapshot.value?.playlists ?? [])
        .filter((playlist) => playlist.is_active)
        .flatMap((playlist) => playlist.items
            .filter((item) => item.is_active && item.media !== null && item.media.is_active)
            .map((item) => ({
                playlist: playlist.name,
                media: item.media as MediaAsset,
                duration_seconds: item.duration_seconds,
            }))),
);

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
    if (localStorage.getItem(AUDIO_STORAGE_KEY) === '1') {
        void restoreAudio();
    }

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

    announcer.disable();

    if (echoClient) {
        for (const channel of subscribedChannels) {
            echoClient.leave(channel);
        }
    }
});

async function loadSnapshot(): Promise<void> {
    try {
        const response = await api.snapshot(displayToken);
        snapshot.value = response;
        error.value = '';
        detectAnnouncements();
        subscribeToSections();
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'No se pudo actualizar la pantalla.';
    }
}

function detectAnnouncements(): void {
    const calls = sections.value
        .flatMap((section) => section.calling)
        .slice()
        .sort((left, right) =>
            (left.called_at ?? '').localeCompare(right.called_at ?? '') || left.id - right.id,
        );

    for (const ticket of calls) {
        const key = `${ticket.called_at ?? ''}|${ticket.call_count}`;
        const previous = announcedCalls.get(ticket.id);

        if (previous === key) {
            continue;
        }

        announcedCalls.set(ticket.id, key);

        if (snapshotLoaded) {
            announcer.announce({
                code: ticket.code,
                label: ticket.label,
                number: ticket.number,
                station_label: ticket.station_label,
            });
        }
    }

    snapshotLoaded = true;
}

async function restoreAudio(): Promise<void> {
    try {
        const running = await announcer.enable();
        audioEnabled.value = running;

        if (!running) {
            announcer.disable();
        }
    } catch {
        announcer.disable();
        audioEnabled.value = false;
    }
}

async function toggleAudio(): Promise<void> {
    audioError.value = '';

    if (audioEnabled.value) {
        announcer.disable();
        audioEnabled.value = false;
        localStorage.setItem(AUDIO_STORAGE_KEY, '0');

        return;
    }

    try {
        const running = await announcer.enable();

        if (!running) {
            announcer.disable();
            throw new Error('El navegador bloqueó el audio. Vuelve a pulsar el botón.');
        }

        audioEnabled.value = true;
        localStorage.setItem(AUDIO_STORAGE_KEY, '1');
    } catch (exception) {
        audioEnabled.value = false;
        audioError.value = exception instanceof Error
            ? exception.message
            : 'No se pudo activar el audio.';
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
</script>

<template>
    <main class="flex min-h-screen flex-col overflow-hidden bg-brand-950 text-white">
        <header class="flex flex-wrap items-end justify-between gap-6 border-b border-brand-800 px-10 py-8">
            <div class="flex items-center gap-5">
                <span class="rounded-2xl bg-white p-2">
                    <img :src="logoUrl" alt="CESSA" class="h-16 w-auto object-contain">
                </span>
                <div>
                    <p class="text-lg uppercase tracking-[0.4em] text-accent-300">
                        {{ display ? display.name : 'Turnos de atención' }}
                    </p>
                    <h1 class="mt-2 text-4xl font-semibold">Pantalla de llamados</h1>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div class="text-right">
                    <p class="font-mono text-6xl font-bold tracking-tight text-slate-100">{{ clockLabel }}</p>
                    <p class="mt-1 text-lg capitalize text-slate-400">{{ dateLabel }}</p>
                </div>

                <div class="flex flex-col items-end gap-1">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-3 text-sm font-semibold transition"
                        :class="audioEnabled
                            ? 'border border-emerald-500/50 bg-emerald-500/15 text-emerald-200'
                            : 'bg-accent-300 text-brand-950 hover:bg-accent-200'"
                        @click="toggleAudio"
                    >
                        {{ audioEnabled ? 'Ring + voz activos' : 'Activar audio' }}
                    </button>
                    <p v-if="audioError" class="max-w-56 text-right text-xs text-red-300">{{ audioError }}</p>
                    <p v-else-if="!speechSupported" class="max-w-56 text-right text-xs text-slate-500">
                        El navegador no soporta voz; se reproduce solo el timbre.
                    </p>
                </div>
            </div>
        </header>

        <section class="flex-1 px-10 py-8">
            <div v-if="layout === 'queue'">
                <QueueBoard :sections="sections" />
                <CallHistory :tickets="recentTickets" />
            </div>

            <template v-else-if="layout === 'mixed'">
                <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <QueueBoard :sections="sections" compact />
                    <MediaStage :items="mediaItems" />
                </div>
                <CallHistory :tickets="recentTickets" />
            </template>

            <div v-else class="h-full min-h-[32rem]">
                <MediaStage :items="mediaItems" />
            </div>
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-800 px-10 py-5 text-sm text-slate-500">
            <p>
                {{ echoClient ? 'Actualización en tiempo real por WebSocket.' : 'Actualización automática cada 5 segundos.' }}
                <span v-if="displayToken">· Pantalla autenticada por token.</span>
            </p>
            <p v-if="error" class="text-red-300">{{ error }}</p>
        </footer>
    </main>
</template>
