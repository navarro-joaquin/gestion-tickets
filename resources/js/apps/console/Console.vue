<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../../shared/api/client';
import { getEcho } from '../../shared/echo';
import { createPrintDriver, type PrintDriverKind } from '../../shared/printing/escpos';
import type {
    AuthUser,
    ConsoleQueue,
    OperatorSession,
    Section,
    Station,
    Ticket,
} from '../../shared/types/api';

const SESSION_STORAGE_KEY = 'console-session-id';

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

const message = ref('');
const error = ref('');
const busy = ref(false);
const printState = ref<'idle' | 'printing' | 'printed' | 'failed'>('idle');
const driverKind = ref<PrintDriverKind>('local');
const printerTarget = ref<string | null>(null);

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
    const storedDriver = localStorage.getItem('print-driver');

    if (storedDriver === 'local' || storedDriver === 'qz') {
        driverKind.value = storedDriver;
    }

    await loadCurrentUser();

    if (!currentUser.value) {
        return;
    }

    await loadSections();

    if (sections.value.length > 0) {
        selectedSectionSlug.value = sections.value[0].slug;
    }

    await loadStations();
    await restoreSession();

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

watch(selectedSectionSlug, async () => {
    if (session.value) {
        return;
    }

    selectedStationId.value = null;
    await loadStations();

    if (stations.value.length > 0) {
        selectedStationId.value = stations.value[0].id;
    }
});

watch(selectedStationId, (stationId) => {
    if (session.value) {
        changeStationId.value = stationId;
    }
});

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

        if (stations.value.length > 0 && !stations.value.some((station) => station.id === selectedStationId.value)) {
            selectedStationId.value = stations.value[0].id;
        }
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

async function openSession(): Promise<void> {
    if (busy.value) {
        return;
    }

    if (!selectedSectionSlug.value || !selectedStationId.value) {
        error.value = 'Seleccione sección y ventanilla para abrir la jornada.';
        return;
    }

    busy.value = true;
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
    }
}

async function useSession(activeSession: OperatorSession): Promise<void> {
    session.value = activeSession;
    selectedSectionSlug.value = activeSession.section?.slug ?? '';
    selectedStationId.value = activeSession.station?.id ?? null;
    changeStationId.value = activeSession.station?.id ?? null;
    localStorage.setItem(SESSION_STORAGE_KEY, String(activeSession.id));

    if (activeSession.section?.slug) {
        const sectionStations = await api.stations(activeSession.section.slug);
        stations.value = sectionStations.data;
    }

    await loadPrinter(activeSession.station?.id ?? null);
    await loadQueue();
    subscribe();
}

async function closeSession(): Promise<void> {
    if (!session.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    message.value = '';

    try {
        const response = await api.closeSession(session.value.id);
        message.value = response.message;
        localStorage.removeItem(SESSION_STORAGE_KEY);
        unsubscribe();
        session.value = null;
        queue.value = null;
        printState.value = 'idle';
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
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
        message.value = response.message;
        await loadPrinter(response.data.station?.id ?? null);
        await loadQueue();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function loadPrinter(stationId: number | null): Promise<void> {
    printerTarget.value = null;

    if (!stationId) {
        return;
    }

    try {
        const response = await api.consolePrinters(stationId);
        const printer = response.data[0];

        if (printer) {
            driverKind.value = printer.driver;
            printerTarget.value = printer.target;
        }
    } catch {
        // La configuración de impresora es opcional; se conserva el valor local.
    }
}

async function loadQueue(): Promise<void> {    if (!session.value?.section?.slug || !currentStationName.value) {
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

async function reprint(): Promise<void> {
    if (busy.value || !activeTicket.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';
    printState.value = 'printing';

    try {
        const response = await api.reprint(activeTicket.value.id);

        try {
            await createPrintDriver(driverKind.value, printerTarget.value).print(response.print.escpos_base64);
            printState.value = 'printed';
            message.value = response.message;
        } catch (printException) {
            printState.value = 'failed';
            const detail = describeError(printException);
            error.value = `No se pudo imprimir: ${detail}`;
            await api.printFailed(activeTicket.value.id, detail);
        }
    } catch (exception) {
        printState.value = 'idle';
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

function changeDriver(): void {
    localStorage.setItem('print-driver', driverKind.value);
}

async function logout(): Promise<void> {
    try {
        await api.logout();
    } catch {
        // La redirección a login es suficiente aunque la API falle.
    }

    window.location.href = '/login';
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
    <main class="min-h-screen bg-slate-950 px-6 py-8 text-white">
        <header class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-6 border-b border-slate-800 pb-6">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Gestión de tickets</p>
                <h1 class="text-3xl font-semibold">Consola de operador</h1>
            </div>

            <div v-if="currentUser" class="flex flex-wrap items-center gap-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Usuario</p>
                    <p class="text-sm font-medium text-slate-100">{{ currentUser.name }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-red-400 hover:text-red-200"
                    @click="logout"
                >
                    Salir
                </button>
            </div>

            <div v-if="session" class="flex flex-wrap items-end gap-4">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Operador</p>
                    <p class="text-sm font-medium text-slate-100">{{ session.operator?.name }}</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sección</p>
                    <p class="text-sm font-medium text-slate-100">{{ session.section?.name }}</p>
                </div>
            </div>
        </header>

        <section v-if="!session" class="mx-auto mt-12 max-w-3xl rounded-3xl border border-slate-800 bg-slate-900 p-8">
            <h2 class="text-2xl font-semibold">Abrir jornada</h2>
            <p class="mt-2 text-sm text-slate-400">
                El operador queda vinculado a una sola sección durante la jornada. La ventanilla puede
                cambiarse después, dejando historial.
            </p>

            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Sección
                    <select
                        v-model="selectedSectionSlug"
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100"
                    >
                        <option v-for="section in sections" :key="section.slug" :value="section.slug">
                            {{ section.name }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Ventanilla
                    <select
                        v-model="selectedStationId"
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100"
                    >
                        <option :value="null" disabled>Seleccione</option>
                        <option v-for="station in stations" :key="station.id" :value="station.id">
                            {{ station.name }}
                        </option>
                    </select>
                </label>
            </div>

            <button
                type="button"
                class="mt-8 rounded-xl bg-sky-500 px-6 py-3 font-semibold text-slate-950 transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="busy"
                @click="openSession"
            >
                {{ busy ? 'Abriendo…' : 'Abrir jornada' }}
            </button>
        </section>

        <section v-else class="mx-auto mt-8 grid max-w-6xl gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">Ticket activo</h2>
                        <p class="mt-1 text-sm text-slate-400">
                            Ventanilla actual: <span class="text-slate-200">{{ currentStationName || 'sin asignar' }}</span>
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-4 py-1 text-sm text-slate-300">
                        {{ waitingCount }} en espera
                    </span>
                </div>

                <div v-if="activeTicket" class="mt-8">
                    <p class="text-7xl font-black tracking-tight">{{ activeTicket.label }}</p>
                    <p class="mt-3 text-xl text-slate-300">
                        {{ activeTicket.service?.name ?? activeTicket.category?.name }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3 text-sm">
                        <span class="rounded-full bg-sky-500/15 px-4 py-1 text-sky-300">
                            {{ activeTicket.status_label }}
                        </span>
                        <span
                            v-if="activeTicket.is_preferential"
                            class="rounded-full bg-fuchsia-500/15 px-4 py-1 text-fuchsia-300"
                        >
                            Preferencial
                        </span>
                        <span
                            v-if="activeTicket.service"
                            class="rounded-full bg-emerald-500/15 px-4 py-1 text-emerald-300"
                        >
                            Servicio: {{ activeTicket.service.name }}
                        </span>
                        <span class="rounded-full bg-slate-800 px-4 py-1 text-slate-300">
                            {{ activeTicket.call_count }} llamado(s)
                        </span>
                        <span class="rounded-full bg-slate-800 px-4 py-1 text-slate-300">
                            {{ activeTicket.station_label }}
                        </span>
                        <span
                            v-if="activeTicket.is_transferred"
                            class="rounded-full bg-amber-500/15 px-4 py-1 text-amber-300"
                        >
                            Transferido desde {{ activeTicket.section?.name }}
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

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-200 transition hover:border-amber-500 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2"
                        :disabled="busy || !activeTicket"
                        @click="reprint"
                    >
                        {{ printState === 'printing' ? 'Reimprimiendo…' : 'Reimprimir ticket' }}
                    </button>
                </div>

                <p v-if="printState === 'printed'" class="mt-4 text-sm text-emerald-300">
                    Ticket enviado a la impresora.
                </p>
                <p v-else-if="printState === 'failed'" class="mt-4 text-sm text-amber-300">
                    Fallo de impresión registrado. Puede reintentar con otro driver.
                </p>
            </div>

            <aside class="space-y-4">
                <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Ventanilla</h2>
                    <div class="mt-4 flex flex-col gap-3">
                        <select
                            v-model="changeStationId"
                            class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100"
                        >
                            <option v-for="station in sessionStations" :key="station.id" :value="station.id">
                                {{ station.name }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-700 px-4 py-2 font-semibold text-slate-200 transition hover:border-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="busy || changeStationId === session.station?.id"
                            @click="changeStation"
                        >
                            Cambiar ventanilla
                        </button>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">
                        El cambio queda registrado en el historial de la sesión.
                    </p>
                </div>

                <div v-if="canTransfer" class="rounded-3xl border border-amber-500/30 bg-amber-500/5 p-6">
                    <h2 class="text-lg font-semibold text-amber-200">Transferir ticket</h2>
                    <p class="mt-2 text-sm text-amber-100/70">
                        Se conserva el código y el número originales; cambia la sección que lo atiende.
                    </p>
                    <div class="mt-4 flex flex-col gap-3">
                        <select
                            v-model="transferSectionSlug"
                            class="rounded-xl border border-amber-500/30 bg-slate-950 px-4 py-3 text-slate-100"
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
                            class="rounded-xl border border-amber-500/30 bg-slate-950 px-4 py-3 text-slate-100 placeholder:text-slate-600"
                        >
                        <button
                            type="button"
                            class="rounded-xl bg-amber-400 px-4 py-2 font-semibold text-slate-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="busy || !transferSectionSlug"
                            @click="transfer"
                        >
                            Transferir
                        </button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Impresora</h2>
                    <label class="mt-4 flex flex-col gap-2 text-sm text-slate-400">
                        Driver
                        <select
                            v-model="driverKind"
                            class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-200"
                            @change="changeDriver"
                        >
                            <option value="local">Servicio local</option>
                            <option value="qz">QZ Tray</option>
                        </select>
                    </label>
                    <p class="mt-4 text-sm text-slate-500">
                        {{ echoClient ? 'Reverb configurado: los cambios se reciben en tiempo real.' : 'Sin Reverb: actualización cada 5 segundos.' }}
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Sesión</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        Abierta el {{ new Date(session.started_at).toLocaleString('es-BO') }}.
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl border border-red-500/40 px-4 py-2 font-semibold text-red-200 transition hover:border-red-400 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="closeSession"
                    >
                        Cerrar sesión
                    </button>
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
