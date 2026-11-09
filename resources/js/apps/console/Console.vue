<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../../shared/api/client';
import { getEcho } from '../../shared/echo';
import type {
    AuthUser,
    ConsoleQueue,
    OperatorSession,
    Section,
    Station,
    Ticket,
} from '../../shared/types/api';

const SESSION_STORAGE_KEY = 'console-session-id';
const logoUrl = '/images/cessa-logo.jpg';

const currentUser = ref<AuthUser | null>(null);
const sections = ref<Section[]>([]);
const stations = ref<Station[]>([]);
const session = ref<OperatorSession | null>(null);
const queue = ref<ConsoleQueue | null>(null);

const selectedSectionSlug = ref('');
const selectedStationId = ref<number | null>(null);
const changeStationId = ref<number | null>(null);
const transferSectionSlug = ref('');
const transferReason = ref('');

const editingStation = ref(false);
const opening = ref(true);

const message = ref('');
const error = ref('');
const busy = ref(false);

const echoClient = getEcho();
let channelName: string | null = null;
let pollingTimer: ReturnType<typeof setInterval> | undefined;

const activeTicket = computed<Ticket | null>(() => queue.value?.active_ticket ?? null);
const waitingCount = computed<number>(() => queue.value?.waiting_count ?? 0);
const currentStationName = computed<string>(() => session.value?.station?.name ?? '');
const sessionStations = computed<Station[]>(() => queue.value?.stations ?? stations.value);

const canRecall = computed<boolean>(
    () => activeTicket.value?.status === 'called' && activeTicket.value.call_count < 2,
);
const canStart = computed<boolean>(() => activeTicket.value?.status === 'called');
const canFinish = computed<boolean>(() => activeTicket.value?.status === 'in_service');
const canCallNext = computed<boolean>(() => activeTicket.value === null);
const canTransfer = computed<boolean>(
    () => activeTicket.value !== null && ['waiting', 'called'].includes(activeTicket.value.status),
);

const transferSections = computed<Section[]>(() => {
    const currentSlug = session.value?.section?.slug;

    return sections.value.filter((section) => section.slug !== currentSlug);
});

onMounted(async () => {
    await loadCurrentUser();

    if (!currentUser.value) {
        opening.value = false;

        return;
    }

    await loadSections();
    selectedSectionSlug.value = preferredSectionSlug();
    await loadStations();
    await restoreSession();

    if (!session.value) {
        await restoreActiveSession();
    }

    if (!session.value) {
        await openSession();
    }

    opening.value = false;

    pollingTimer = setInterval(() => {
        void loadQueue();
    }, 5000);
});

onBeforeUnmount(() => {
    if (pollingTimer) {
        clearInterval(pollingTimer);
    }

    unsubscribe();
});

watch(selectedStationId, (stationId) => {
    if (session.value) {
        changeStationId.value = stationId;
    }
});

function preferredSectionSlug(): string {
    const defaultSlug = currentUser.value?.default_section?.slug;

    if (defaultSlug && sections.value.some((section) => section.slug === defaultSlug)) {
        return defaultSlug;
    }

    return sections.value[0]?.slug ?? '';
}

function startStationChange(): void {
    changeStationId.value = session.value?.station?.id ?? null;
    editingStation.value = true;
}

function cancelStationChange(): void {
    changeStationId.value = session.value?.station?.id ?? null;
    editingStation.value = false;
}

async function loadSections(): Promise<void> {
    try {
        const response = await api.sections();
        sections.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadCurrentUser(): Promise<void> {
    try {
        const response = await api.me();
        currentUser.value = response.data;
    } catch (exception) {
        if (exception instanceof Error && 'status' in exception && exception.status === 401) {
            window.location.href = '/login';
            return;
        }

        error.value = describeError(exception);
    }
}

async function loadStations(): Promise<void> {
    if (!selectedSectionSlug.value) {
        stations.value = [];
        return;
    }

    try {
        const response = await api.stations(selectedSectionSlug.value);
        stations.value = response.data;

        const defaultStationId = currentUser.value?.default_section?.slug === selectedSectionSlug.value
            ? currentUser.value?.default_station?.id ?? null
            : null;

        const preferredStation = stations.value.find((station) => station.id === defaultStationId)
            ?? stations.value.find((station) => station.id === selectedStationId.value)
            ?? stations.value[0];

        selectedStationId.value = preferredStation?.id ?? null;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function restoreSession(): Promise<void> {
    const stored = localStorage.getItem(SESSION_STORAGE_KEY);

    if (!stored) {
        return;
    }

    try {
        const response = await api.session(Number(stored));

        if (!response.data.is_active) {
            localStorage.removeItem(SESSION_STORAGE_KEY);
            return;
        }

        await useSession(response.data);
    } catch {
        localStorage.removeItem(SESSION_STORAGE_KEY);
    }
}

async function restoreActiveSession(): Promise<void> {
    if (!selectedSectionSlug.value) {
        return;
    }

    try {
        const response = await api.queue(selectedSectionSlug.value);
        const activeSession = response.data.session;

        if (activeSession) {
            await useSession(activeSession);
        }
    } catch {
        // Si no se puede consultar, se intenta abrir una jornada nueva.
    }
}

async function openSession(): Promise<void> {
    if (busy.value) {
        return;
    }

    if (!selectedSectionSlug.value || !selectedStationId.value) {
        opening.value = false;
        error.value = 'Su usuario no tiene una sección y ventanilla por defecto configuradas. Contacte al administrador.';
        return;
    }

    busy.value = true;
    opening.value = !session.value;
    error.value = '';
    message.value = '';

    try {
        const response = await api.openSession(
            selectedSectionSlug.value,
            selectedStationId.value,
        );

        await useSession(response.data);
        message.value = response.message;
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
        opening.value = false;
    }
}

async function useSession(activeSession: OperatorSession): Promise<void> {
    session.value = activeSession;
    selectedSectionSlug.value = activeSession.section?.slug ?? '';
    selectedStationId.value = activeSession.station?.id ?? null;
    changeStationId.value = activeSession.station?.id ?? null;
    editingStation.value = false;
    localStorage.setItem(SESSION_STORAGE_KEY, String(activeSession.id));

    if (activeSession.section?.slug) {
        const sectionStations = await api.stations(activeSession.section.slug);
        stations.value = sectionStations.data;
    }

    await loadQueue();
    subscribe();
}

async function closeSessionAndLogout(): Promise<void> {
    if (!session.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    message.value = '';

    try {
        await api.closeSession(session.value.id);
        localStorage.removeItem(SESSION_STORAGE_KEY);
        unsubscribe();
        session.value = null;
        queue.value = null;
    } catch (exception) {
        error.value = describeError(exception);
        busy.value = false;

        return;
    }

    try {
        await api.logout();
    } catch {
        // La redirección cierra la sesión del navegador de todos modos.
    }

    window.location.href = '/login';
}

async function changeStation(): Promise<void> {
    if (!session.value || !changeStationId.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    message.value = '';

    try {
        const response = await api.changeStation(session.value.id, changeStationId.value);
        session.value = response.data;
        selectedStationId.value = response.data.station?.id ?? null;
        changeStationId.value = response.data.station?.id ?? null;
        editingStation.value = false;
        message.value = response.message;
        await loadQueue();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function loadQueue(): Promise<void> {
    if (!session.value?.section?.slug || !currentStationName.value) {
        return;
    }

    try {
        const response = await api.queue(
            session.value.section.slug,
            currentStationName.value,
        );

        queue.value = response.data;
        session.value = response.data.session ?? session.value;
        error.value = '';
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function subscribe(): void {
    if (!echoClient || !session.value?.section?.slug) {
        return;
    }

    unsubscribe();
    channelName = `sections.${session.value.section.slug}`;

    echoClient.channel(channelName).listen('.queue.changed', () => {
        void loadQueue();
    });
}

function unsubscribe(): void {
    if (echoClient && channelName) {
        echoClient.leave(channelName);
    }

    channelName = null;
}

async function callNext(): Promise<void> {
    if (busy.value || !session.value?.section?.slug || !currentStationName.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.next(
            session.value.section.slug,
            currentStationName.value,
        );

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
    message.value = '';
    error.value = '';

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

async function transfer(): Promise<void> {
    if (!activeTicket.value || !transferSectionSlug.value || busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.transfer(
            activeTicket.value.id,
            transferSectionSlug.value,
            null,
            transferReason.value,
        );

        message.value = response.message;
        transferSectionSlug.value = '';
        transferReason.value = '';
        await loadQueue();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function describeError(exception: unknown): string {
    if (exception instanceof Error && 'status' in exception && exception.status === 401) {
        window.location.href = '/login';

        return 'La sesión expiró.';
    }

    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <main class="min-h-screen bg-slate-100 px-6 py-8 text-slate-900">
        <header class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-6 border-b border-slate-200 pb-6">
            <div class="flex items-center gap-4">
                <span class="rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
                    <img :src="logoUrl" alt="CESSA" class="h-12 w-auto object-contain">
                </span>
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-brand-700">CESSA · Gestión de tickets</p>
                    <h1 class="text-3xl font-semibold">Consola de operador</h1>
                </div>
            </div>

            <div v-if="session" class="flex flex-wrap items-end gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Operador</p>
                    <p class="text-sm font-medium text-slate-800">{{ session.operator?.name }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sección</p>
                    <p class="text-sm font-medium text-slate-800">{{ session.section?.name }}</p>
                </div>
            </div>
        </header>

        <section v-if="!session" class="mx-auto mt-12 max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <template v-if="opening">
                <h2 class="text-2xl font-semibold">Abriendo jornada…</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Se está usando su sección y ventanilla por defecto.
                </p>
            </template>

            <template v-else>
                <h2 class="text-2xl font-semibold">No se pudo abrir la jornada</h2>
                <p v-if="error" class="mt-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </p>
                <button
                    type="button"
                    class="mt-6 rounded-xl bg-accent-300 px-6 py-3 font-semibold text-brand-950 transition hover:bg-accent-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                    :disabled="busy"
                    @click="openSession"
                >
                    Reintentar
                </button>
            </template>
        </section>

        <section v-else class="mx-auto mt-8 grid max-w-6xl gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">Ticket activo</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Ventanilla actual: <span class="font-medium text-slate-900">{{ currentStationName || 'sin asignar' }}</span>
                        </p>
                    </div>
                    <span class="rounded-full bg-brand-50 px-4 py-1 text-sm font-medium text-brand-800">
                        {{ waitingCount }} en espera
                    </span>
                </div>

                <div v-if="activeTicket" class="mt-8">
                    <p class="text-7xl font-black tracking-tight text-brand-900">{{ activeTicket.label }}</p>
                    <p class="mt-3 text-xl text-slate-700">
                        {{ activeTicket.service?.name ?? activeTicket.category?.name }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3 text-sm">
                        <span class="rounded-full bg-accent-100 px-4 py-1 font-medium text-brand-900">
                            {{ activeTicket.status_label }}
                        </span>
                        <span
                            v-if="activeTicket.is_preferential"
                            class="rounded-full bg-fuchsia-100 px-4 py-1 font-medium text-fuchsia-800"
                        >
                            Preferencial
                        </span>
                        <span
                            v-if="activeTicket.service"
                            class="rounded-full bg-emerald-100 px-4 py-1 font-medium text-emerald-800"
                        >
                            Servicio: {{ activeTicket.service.name }}
                        </span>
                        <span class="rounded-full bg-brand-50 px-4 py-1 font-medium text-brand-800">
                            {{ activeTicket.call_count }} llamado(s)
                        </span>
                        <span class="rounded-full bg-brand-50 px-4 py-1 font-medium text-brand-800">
                            {{ activeTicket.station_label }}
                        </span>
                        <span
                            v-if="activeTicket.is_transferred"
                            class="rounded-full bg-amber-100 px-4 py-1 font-medium text-amber-800"
                        >
                            Transferido desde {{ activeTicket.section?.name }}
                        </span>
                    </div>
                </div>

                <div v-else class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-slate-500">
                    No hay un ticket activo en esta ventanilla.
                </div>

                <div class="mt-10 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-xl bg-accent-300 px-5 py-3 font-semibold text-brand-950 transition hover:bg-accent-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :disabled="busy || !canCallNext"
                        @click="callNext"
                    >
                        Llamar siguiente
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :disabled="busy || !canRecall"
                        @click="runAction('recall')"
                    >
                        Rellamar
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :disabled="busy || !canStart"
                        @click="runAction('start')"
                    >
                        Iniciar atención
                    </button>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :disabled="busy || !canFinish"
                        @click="runAction('finish')"
                    >
                        Finalizar
                    </button>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Ventanilla</h2>

                    <template v-if="!editingStation">
                        <p class="mt-4 text-2xl font-semibold text-slate-900">
                            {{ currentStationName || 'Sin asignar' }}
                        </p>
                        <button
                            type="button"
                            class="mt-4 rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                            :disabled="busy"
                            @click="startStationChange"
                        >
                            Cambiar
                        </button>
                    </template>

                    <template v-else>
                        <div class="mt-4 flex flex-col gap-3">
                            <select
                                v-model="changeStationId"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                            >
                                <option v-for="station in sessionStations" :key="station.id" :value="station.id">
                                    {{ station.name }}
                                </option>
                            </select>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    class="rounded-xl bg-accent-300 px-4 py-2 font-semibold text-brand-950 transition hover:bg-accent-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                                    :disabled="busy || changeStationId === session.station?.id"
                                    @click="changeStation"
                                >
                                    Confirmar cambio
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                                    :disabled="busy"
                                    @click="cancelStationChange"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </template>

                    <p class="mt-4 text-xs text-slate-500">
                        El cambio queda registrado en el historial de la sesión.
                    </p>
                </div>

                <div v-if="canTransfer" class="rounded-3xl border border-amber-300 bg-amber-50 p-6">
                    <h2 class="text-lg font-semibold text-amber-800">Transferir ticket</h2>
                    <p class="mt-2 text-sm text-amber-800">
                        Se conserva el código y el número originales; cambia la sección que lo atiende.
                    </p>
                    <div class="mt-4 flex flex-col gap-3">
                        <select
                            v-model="transferSectionSlug"
                            class="rounded-xl border border-amber-300 bg-white px-4 py-3 text-slate-900"
                        >
                            <option value="">Sección de destino</option>
                            <option v-for="section in transferSections" :key="section.slug" :value="section.slug">
                                {{ section.name }}
                            </option>
                        </select>
                        <input
                            v-model="transferReason"
                            type="text"
                            maxlength="255"
                            placeholder="Motivo (opcional)"
                            class="rounded-xl border border-amber-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400"
                        >
                        <button
                            type="button"
                            class="rounded-xl bg-amber-400 px-4 py-2 font-semibold text-brand-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                            :disabled="busy || !transferSectionSlug"
                            @click="transfer"
                        >
                            Transferir
                        </button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Conexión</h2>
                    <p class="mt-4 text-sm text-slate-500">
                        {{ echoClient ? 'Reverb configurado: los cambios se reciben en tiempo real.' : 'Sin Reverb: actualización cada 5 segundos.' }}
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Jornada</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Abierta el {{ new Date(session.started_at).toLocaleString('es-BO') }}.
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        Al cerrar sesión se libera la ventanilla y se cierra la sesión del navegador.
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl border border-red-300 px-4 py-2 font-semibold text-red-700 transition hover:border-red-400 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :disabled="busy"
                        @click="closeSessionAndLogout"
                    >
                        Cerrar sesión
                    </button>
                </div>

                <div v-if="message" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-800">
                    {{ message }}
                </div>

                <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-6 text-red-700">
                    {{ error }}
                </div>
            </aside>
        </section>
    </main>
</template>
