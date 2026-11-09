<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, type PriorityProfilePayload, type ServicePayload } from '../../shared/api/client';
import AuditPanel from './components/AuditPanel.vue';
import DisplaysPanel from './components/DisplaysPanel.vue';
import KiosksPanel from './components/KiosksPanel.vue';
import MediaPanel from './components/MediaPanel.vue';
import PlaylistsPanel from './components/PlaylistsPanel.vue';
import PrintersPanel from './components/PrintersPanel.vue';
import ReportsPanel from './components/ReportsPanel.vue';
import SectionsPanel from './components/SectionsPanel.vue';
import StationsPanel from './components/StationsPanel.vue';
import UsersPanel from './components/UsersPanel.vue';
import type {
    AdminSettings,
    AuthUser,
    Operator,
    PriorityProfile,
    Section,
    Service,
    SimulatedQueue,
} from '../../shared/types/api';

type Tab = 'settings' | 'priorities' | 'services' | 'simulator' | 'sections' | 'stations' | 'users' | 'kiosks' | 'printers' | 'media' | 'playlists' | 'displays' | 'audit' | 'reports';

interface ProfileForm {
    name: string;
    description: string;
    general_ratio: number;
    preferential_ratio: number;
    aging_minutes: number;
    max_consecutive_preferential: number;
    is_active: boolean;
    effective_from: string;
    effective_to: string;
}

interface ServiceForm {
    section: string;
    slug: string;
    name: string;
    label: string;
    description: string;
    is_active: boolean;
    available_days: number[];
    available_from: string;
    available_to: string;
    effective_from: string;
    effective_to: string;
    sort_order: number;
    operator_ids: number[];
}

interface TabItem {
    id: Tab;
    label: string;
    group: string;
    description: string;
}

const tabs: TabItem[] = [
    { id: 'settings', label: 'Ajustes', group: 'Configuración', description: 'Cierre de jornada, retención y parámetros generales.' },
    { id: 'priorities', label: 'Prioridades', group: 'Configuración', description: 'Perfiles de prioridad, proporción y reglas de aging.' },
    { id: 'services', label: 'Servicios', group: 'Configuración', description: 'Catálogo de servicios exclusivos y autorizaciones.' },
    { id: 'sections', label: 'Secciones', group: 'Operación', description: 'Secciones, códigos y matriz de categorías.' },
    { id: 'stations', label: 'Ventanillas', group: 'Operación', description: 'Ventanillas activas de cada sección.' },
    { id: 'users', label: 'Usuarios', group: 'Operación', description: 'Operadores, supervisores y administradores.' },
    { id: 'simulator', label: 'Simulador', group: 'Operación', description: 'Simulación de la secuencia de llamados.' },
    { id: 'kiosks', label: 'Quioscos', group: 'Dispositivos y contenido', description: 'Tokens y secciones habilitadas por quiosco.' },
    { id: 'printers', label: 'Impresoras', group: 'Dispositivos y contenido', description: 'Impresoras ESC/POS asociadas a cada quiosco.' },
    { id: 'media', label: 'Contenidos', group: 'Dispositivos y contenido', description: 'Material multimedia para las pantallas.' },
    { id: 'playlists', label: 'Playlists', group: 'Dispositivos y contenido', description: 'Secuencias de contenido por pantalla.' },
    { id: 'displays', label: 'Pantallas', group: 'Dispositivos y contenido', description: 'Pantallas, layouts y secciones asignadas.' },
    { id: 'reports', label: 'Reportes', group: 'Control', description: 'Indicadores, detalle y exportaciones.' },
    { id: 'audit', label: 'Auditoría', group: 'Control', description: 'Historial de cambios administrativos.' },
];

const activeTab = ref<Tab>('settings');
const sidebarOpen = ref(false);
const logoUrl = '/images/cessa-logo.jpg';
const currentUser = ref<AuthUser | null>(null);

const activeTabMeta = computed<TabItem>(() => tabs.find((tab) => tab.id === activeTab.value) ?? tabs[0]);

const navGroups = computed<Array<{ label: string; items: TabItem[] }>>(() => {
    const groups: Array<{ label: string; items: TabItem[] }> = [];

    for (const tab of tabs) {
        let group = groups.find((item) => item.label === tab.group);

        if (!group) {
            group = { label: tab.group, items: [] };
            groups.push(group);
        }

        group.items.push(tab);
    }

    return groups;
});

function selectTab(tab: Tab): void {
    activeTab.value = tab;
    sidebarOpen.value = false;
}

const settings = ref<AdminSettings | null>(null);
const closingTime = ref('16:30');
const retentionMonths = ref(12);

const profiles = ref<PriorityProfile[]>([]);
const editingProfileId = ref<number | null>(null);
const profileForm = ref<ProfileForm>(emptyProfileForm());

const services = ref<Service[]>([]);
const sections = ref<Section[]>([]);
const adminOperators = ref<Operator[]>([]);
const editingServiceId = ref<number | null>(null);
const serviceForm = ref<ServiceForm>(emptyServiceForm());

const simulatorForm = ref({
    general_count: 6,
    preferential_count: 3,
    profile_id: null as number | null,
});
const simulation = ref<SimulatedQueue | null>(null);

const message = ref('');
const error = ref('');
const busy = ref(false);

const nextClosingLabel = computed<string>(() => {
    if (!settings.value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(settings.value.next_closing_at));
});

const dayLabels: Array<{ value: number; label: string }> = [
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mié' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sáb' },
    { value: 7, label: 'Dom' },
];

onMounted(async () => {
    await loadCurrentUser();

    if (!currentUser.value) {
        return;
    }

    await Promise.all([
        loadSettings(),
        loadProfiles(),
        loadServices(),
        loadSections(),
        loadAdminOperators(),
    ]);
});

async function loadCurrentUser(): Promise<void> {
    try {
        const response = await api.me();
        currentUser.value = response.data;
    } catch (exception) {
        handleUnauthorized(exception);
    }
}

async function loadSettings(): Promise<void> {
    try {
        const response = await api.adminSettings();
        settings.value = response.data;
        closingTime.value = response.data.closing_time;
        retentionMonths.value = response.data.retention_months;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadProfiles(): Promise<void> {
    try {
        const response = await api.priorityProfiles();
        profiles.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadServices(): Promise<void> {
    try {
        const response = await api.services();
        services.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadSections(): Promise<void> {
    try {
        const response = await api.sections();
        sections.value = response.data;

        if (!serviceForm.value.section && sections.value.length > 0) {
            serviceForm.value.section = sections.value[0].slug;
        }
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadAdminOperators(): Promise<void> {
    try {
        const response = await api.adminOperators();
        adminOperators.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function saveSettings(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.updateAdminSettings(closingTime.value, retentionMonths.value);
        settings.value = response.data;
        closingTime.value = response.data.closing_time;
        retentionMonths.value = response.data.retention_months;
        message.value = response.message;
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function editProfile(profile: PriorityProfile): void {
    editingProfileId.value = profile.id;
    profileForm.value = {
        name: profile.name,
        description: profile.description ?? '',
        general_ratio: profile.general_ratio,
        preferential_ratio: profile.preferential_ratio,
        aging_minutes: profile.aging_minutes,
        max_consecutive_preferential: profile.max_consecutive_preferential,
        is_active: profile.is_active,
        effective_from: profile.effective_from ?? '',
        effective_to: profile.effective_to ?? '',
    };
    activeTab.value = 'priorities';
}

function resetProfileForm(): void {
    editingProfileId.value = null;
    profileForm.value = emptyProfileForm();
}

async function saveProfile(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload: PriorityProfilePayload = {
        name: profileForm.value.name,
        description: nullable(profileForm.value.description),
        general_ratio: Number(profileForm.value.general_ratio),
        preferential_ratio: Number(profileForm.value.preferential_ratio),
        aging_minutes: Number(profileForm.value.aging_minutes),
        max_consecutive_preferential: Number(profileForm.value.max_consecutive_preferential),
        is_active: profileForm.value.is_active,
        effective_from: nullable(profileForm.value.effective_from),
        effective_to: nullable(profileForm.value.effective_to),
    };

    try {
        const response = editingProfileId.value
            ? await api.updatePriorityProfile(editingProfileId.value, payload)
            : await api.createPriorityProfile(payload);

        message.value = response.message;
        resetProfileForm();
        await loadProfiles();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function removeProfile(profile: PriorityProfile): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deletePriorityProfile(profile.id);
        message.value = response.message;
        await loadProfiles();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function editService(service: Service): void {
    editingServiceId.value = service.id;
    serviceForm.value = {
        section: service.section?.slug ?? sections.value[0]?.slug ?? '',
        slug: service.slug,
        name: service.name,
        label: service.label,
        description: service.description ?? '',
        is_active: service.is_active,
        available_days: service.available_days ?? [],
        available_from: (service.available_from ?? '').slice(0, 5),
        available_to: (service.available_to ?? '').slice(0, 5),
        effective_from: service.effective_from ?? '',
        effective_to: service.effective_to ?? '',
        sort_order: service.sort_order,
        operator_ids: (service.operators ?? []).map((operator) => operator.id),
    };
    activeTab.value = 'services';
}

function resetServiceForm(): void {
    editingServiceId.value = null;
    serviceForm.value = emptyServiceForm(sections.value[0]?.slug ?? '');
}

function toggleDay(day: number): void {
    const days = serviceForm.value.available_days;

    serviceForm.value.available_days = days.includes(day)
        ? days.filter((value) => value !== day)
        : [...days, day].sort((left, right) => left - right);
}

async function saveService(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload: ServicePayload = {
        section: serviceForm.value.section,
        slug: serviceForm.value.slug,
        name: serviceForm.value.name,
        label: serviceForm.value.label,
        description: nullable(serviceForm.value.description),
        is_active: serviceForm.value.is_active,
        available_days: serviceForm.value.available_days.length > 0
            ? serviceForm.value.available_days
            : null,
        available_from: nullable(serviceForm.value.available_from),
        available_to: nullable(serviceForm.value.available_to),
        effective_from: nullable(serviceForm.value.effective_from),
        effective_to: nullable(serviceForm.value.effective_to),
        sort_order: Number(serviceForm.value.sort_order),
        operator_ids: serviceForm.value.operator_ids,
    };

    try {
        const response = editingServiceId.value
            ? await api.updateService(editingServiceId.value, payload)
            : await api.createService(payload);

        message.value = response.message;
        resetServiceForm();
        await loadServices();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function removeService(service: Service): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteService(service.id);
        message.value = response.message;
        await loadServices();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function runSimulation(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.simulateQueue(
            Number(simulatorForm.value.general_count),
            Number(simulatorForm.value.preferential_count),
            simulatorForm.value.profile_id,
        );

        simulation.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function logout(): Promise<void> {
    try {
        await api.logout();
    } catch {
        // La redirección es suficiente.
    }

    window.location.href = '/login';
}

function emptyProfileForm(): ProfileForm {
    return {
        name: '',
        description: '',
        general_ratio: 2,
        preferential_ratio: 1,
        aging_minutes: 30,
        max_consecutive_preferential: 1,
        is_active: true,
        effective_from: '',
        effective_to: '',
    };
}

function emptyServiceForm(section = ''): ServiceForm {
    return {
        section,
        slug: '',
        name: '',
        label: '',
        description: '',
        is_active: true,
        available_days: [],
        available_from: '',
        available_to: '',
        effective_from: '',
        effective_to: '',
        sort_order: 0,
        operator_ids: [],
    };
}

function nullable(value: string): string | null {
    return value.trim() === '' ? null : value.trim();
}

function handleUnauthorized(exception: unknown): void {
    if (exception instanceof Error && 'status' in exception && exception.status === 401) {
        window.location.href = '/login';
        return;
    }

    error.value = describeError(exception);
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <header class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
            <div class="flex items-center gap-3">
                <img
                    :src="logoUrl"
                    alt="CESSA"
                    class="h-10 w-auto rounded-lg border border-slate-200 object-contain"
                >
                <div>
                    <p class="text-sm font-bold tracking-wide text-brand-900">CESSA</p>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Administración</p>
                </div>
            </div>
            <button
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-400 hover:text-brand-700"
                @click="sidebarOpen = !sidebarOpen"
            >
                {{ sidebarOpen ? 'Cerrar' : 'Menú' }}
            </button>
        </header>

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-brand-950/60 lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-brand-950 text-brand-50 shadow-2xl transition-transform duration-200"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex items-center gap-3 border-b border-brand-800 px-5 py-5">
                <span class="rounded-xl bg-white p-1.5">
                    <img :src="logoUrl" alt="CESSA" class="h-10 w-auto object-contain">
                </span>
                <div>
                    <p class="text-lg font-bold tracking-wide text-white">CESSA</p>
                    <p class="text-[11px] uppercase tracking-[0.24em] text-accent-300">Gestión de tickets</p>
                </div>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div v-for="group in navGroups" :key="group.label">
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-300">
                        {{ group.label }}
                    </p>
                    <div class="space-y-1">
                        <button
                            v-for="tab in group.items"
                            :key="tab.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
                            :class="activeTab === tab.id
                                ? 'bg-accent-300 text-brand-950 shadow-sm'
                                : 'text-brand-100 hover:bg-brand-900 hover:text-white'"
                            @click="selectTab(tab.id)"
                        >
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="activeTab === tab.id ? 'bg-brand-900' : 'bg-accent-300/70'"
                            />
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </nav>

            <div v-if="currentUser" class="border-t border-brand-800 p-4">
                <div class="rounded-2xl bg-brand-900/70 p-4">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-brand-300">Sesión</p>
                    <p class="mt-1 truncate text-sm font-semibold text-white">{{ currentUser.name }}</p>
                    <p class="text-xs text-brand-200">{{ currentUser.role_label }}</p>
                    <button
                        type="button"
                        class="mt-3 w-full rounded-lg border border-brand-700 px-3 py-2 text-xs font-semibold text-brand-100 transition hover:border-accent-300 hover:text-accent-200"
                        @click="logout"
                    >
                        Cerrar sesión
                    </button>
                </div>
            </div>
        </aside>

        <div class="lg:pl-72">
            <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
                <header class="border-b border-slate-200 pb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-700">Panel de administración</p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ activeTabMeta.label }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ activeTabMeta.description }}</p>
                </header>

                <p v-if="message" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                    {{ message }}
                </p>
                <p v-if="error" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
                    {{ error }}
                </p>

            <section v-if="activeTab === 'settings'" class="mt-8 rounded-3xl border border-slate-200 bg-white p-8">
                <h2 class="text-xl font-semibold">Cierre de jornada y retención</h2>
                <p class="mt-2 text-sm text-slate-500">
                    A esta hora se expiran los tickets en espera, se marcan como no presentados
                    los llamados sin atención y se finalizan los que estaban en atención. Los
                    tickets cerrados se archivan y eliminan al superar la retención.
                </p>

                <div class="mt-8 flex flex-wrap items-end gap-4">
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Hora de cierre
                        <input
                            v-model="closingTime"
                            type="time"
                            step="60"
                            class="w-40 rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-lg text-slate-900"
                        >
                    </label>

                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Retención (meses)
                        <input
                            v-model.number="retentionMonths"
                            type="number"
                            min="1"
                            max="120"
                            class="w-40 rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-lg text-slate-900"
                        >
                    </label>

                    <button
                        type="button"
                        class="rounded-xl bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="saveSettings"
                    >
                        {{ busy ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>

                <dl class="mt-8 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <dt class="text-slate-500">Zona horaria</dt>
                        <dd class="mt-1 text-slate-800">{{ settings?.timezone ?? '—' }}</dd>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <dt class="text-slate-500">Próximo cierre</dt>
                        <dd class="mt-1 text-slate-800">{{ nextClosingLabel || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section v-else-if="activeTab === 'priorities'" class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                <form class="rounded-3xl border border-slate-200 bg-white p-8" @submit.prevent="saveProfile">
                    <h2 class="text-xl font-semibold">
                        {{ editingProfileId ? 'Editar perfil' : 'Nuevo perfil' }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        La proporción indica cuántos tickets generales se llaman por cada preferencial.
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-2 text-sm text-slate-700 sm:col-span-2">
                            Nombre
                            <input v-model="profileForm.name" required maxlength="80" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700 sm:col-span-2">
                            Descripción
                            <input v-model="profileForm.description" maxlength="255" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Ratio general
                            <input v-model.number="profileForm.general_ratio" type="number" min="1" max="20" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Ratio preferencial
                            <input v-model.number="profileForm.preferential_ratio" type="number" min="0" max="20" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Aging (minutos)
                            <input v-model.number="profileForm.aging_minutes" type="number" min="0" max="1440" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Máx. preferenciales seguidos
                            <input v-model.number="profileForm.max_consecutive_preferential" type="number" min="1" max="20" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Vigente desde
                            <input v-model="profileForm.effective_from" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Vigente hasta
                            <input v-model="profileForm.effective_to" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                    </div>

                    <label class="mt-4 flex items-center gap-3 text-sm text-slate-700">
                        <input v-model="profileForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                        Perfil activo
                    </label>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50" :disabled="busy">
                            {{ editingProfileId ? 'Guardar cambios' : 'Crear perfil' }}
                        </button>
                        <button v-if="editingProfileId" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700" @click="resetProfileForm">
                            Cancelar
                        </button>
                    </div>
                </form>

                <div class="space-y-4">
                    <article v-for="profile in profiles" :key="profile.id" class="rounded-3xl border border-slate-200 bg-white p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold">{{ profile.name }}</h3>
                                <p class="text-sm text-slate-500">{{ profile.description || 'Sin descripción' }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="profile.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ profile.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><dt class="text-slate-500">General</dt><dd class="text-slate-800">{{ profile.general_ratio }}</dd></div>
                            <div><dt class="text-slate-500">Preferencial</dt><dd class="text-slate-800">{{ profile.preferential_ratio }}</dd></div>
                            <div><dt class="text-slate-500">Aging</dt><dd class="text-slate-800">{{ profile.aging_minutes }} min</dd></div>
                            <div><dt class="text-slate-500">Máx. seguidos</dt><dd class="text-slate-800">{{ profile.max_consecutive_preferential }}</dd></div>
                        </dl>
                        <div class="mt-4 flex gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="editProfile(profile)">
                                Editar
                            </button>
                            <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" @click="removeProfile(profile)">
                                Eliminar
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section v-else-if="activeTab === 'services'" class="mt-8 space-y-6">
                <form class="rounded-3xl border border-slate-200 bg-white p-8" @submit.prevent="saveService">
                    <h2 class="text-xl font-semibold">{{ editingServiceId ? 'Editar servicio' : 'Nuevo servicio' }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        El servicio usa el código general de la sección y añade su etiqueta al número.
                    </p>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Sección
                            <select v-model="serviceForm.section" required class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                                <option v-for="section in sections" :key="section.slug" :value="section.slug">{{ section.name }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Slug
                            <input v-model="serviceForm.slug" required maxlength="80" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Nombre
                            <input v-model="serviceForm.name" required maxlength="120" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Etiqueta del ticket
                            <input v-model="serviceForm.label" required maxlength="20" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700 md:col-span-2">
                            Descripción
                            <input v-model="serviceForm.description" maxlength="255" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Disponible desde
                            <input v-model="serviceForm.available_from" type="time" step="60" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Disponible hasta
                            <input v-model="serviceForm.available_to" type="time" step="60" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Vigente desde
                            <input v-model="serviceForm.effective_from" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Vigente hasta
                            <input v-model="serviceForm.effective_to" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                        <label class="flex flex-col gap-2 text-sm text-slate-700">
                            Orden
                            <input v-model.number="serviceForm.sort_order" type="number" min="0" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        </label>
                    </div>

                    <div class="mt-4">
                        <p class="text-sm text-slate-700">Días de atención</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="day in dayLabels"
                                :key="day.value"
                                type="button"
                                class="rounded-lg px-3 py-2 text-sm font-semibold"
                                :class="serviceForm.available_days.includes(day.value)
                                    ? 'bg-brand-700 text-white'
                                    : 'border border-slate-300 text-slate-700'"
                                @click="toggleDay(day.value)"
                            >
                                {{ day.label }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Sin días seleccionados: disponible todos los días.</p>
                    </div>

                    <div class="mt-4">
                        <p class="text-sm text-slate-700">Operadores autorizados</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label v-for="operator in adminOperators" :key="operator.id" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                <input v-model="serviceForm.operator_ids" type="checkbox" :value="operator.id" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                                {{ operator.name }}
                            </label>
                        </div>
                        <p v-if="adminOperators.length === 0" class="mt-2 text-xs text-slate-500">No hay operadores activos.</p>
                    </div>

                    <label class="mt-4 flex items-center gap-3 text-sm text-slate-700">
                        <input v-model="serviceForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                        Servicio activo
                    </label>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50" :disabled="busy">
                            {{ editingServiceId ? 'Guardar cambios' : 'Crear servicio' }}
                        </button>
                        <button v-if="editingServiceId" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700" @click="resetServiceForm">
                            Cancelar
                        </button>
                    </div>
                </form>

                <div class="grid gap-4 md:grid-cols-2">
                    <article v-for="service in services" :key="service.id" class="rounded-3xl border border-slate-200 bg-white p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold">{{ service.name }}</h3>
                                <p class="text-sm text-slate-500">{{ service.section?.name }} · {{ service.label }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="service.is_available_now ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ service.is_available_now ? 'Disponible ahora' : 'Fuera de ventana' }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">
                            {{ service.available_from || '—' }} a {{ service.available_to || '—' }}
                            · {{ (service.available_days ?? []).length > 0 ? (service.available_days ?? []).join(', ') : 'todos los días' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Operadores: {{ (service.operators ?? []).map((operator) => operator.name).join(', ') || 'sin restricción' }}
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="editService(service)">
                                Editar
                            </button>
                            <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" @click="removeService(service)">
                                Eliminar
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section v-else-if="activeTab === 'simulator'" class="mt-8 rounded-3xl border border-slate-200 bg-white p-8">
                <h2 class="text-xl font-semibold">Simulador de cola</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Calcula la secuencia de llamados con la proporción y el límite de preferenciales consecutivos.
                </p>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Tickets generales
                        <input v-model.number="simulatorForm.general_count" type="number" min="0" max="200" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                    </label>
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Tickets preferenciales
                        <input v-model.number="simulatorForm.preferential_count" type="number" min="0" max="200" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                    </label>
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Perfil
                        <select v-model="simulatorForm.profile_id" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                            <option :value="null">Perfil vigente</option>
                            <option v-for="profile in profiles" :key="profile.id" :value="profile.id">{{ profile.name }}</option>
                        </select>
                    </label>
                </div>

                <button type="button" class="mt-6 rounded-xl bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50" :disabled="busy" @click="runSimulation">
                    Simular
                </button>

                <div v-if="simulation" class="mt-8">
                    <p class="text-sm text-slate-500">
                        Perfil <span class="text-slate-800">{{ simulation.profile.name }}</span> ·
                        {{ simulation.totals.general }} generales · {{ simulation.totals.preferential }} preferenciales
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="step in simulation.sequence"
                            :key="step.position"
                            class="rounded-xl px-3 py-2 text-sm font-semibold"
                            :class="step.group === 'preferential'
                                ? 'bg-fuchsia-100 text-fuchsia-800'
                                : 'bg-brand-100 text-brand-800'"
                            :title="step.reason"
                        >
                            {{ step.position }}. {{ step.group === 'preferential' ? 'P' : 'G' }}
                        </span>
                    </div>
                </div>
            </section>

            <SectionsPanel v-else-if="activeTab === 'sections'" />
            <StationsPanel v-else-if="activeTab === 'stations'" />
            <UsersPanel v-else-if="activeTab === 'users'" />
            <KiosksPanel v-else-if="activeTab === 'kiosks'" />
            <PrintersPanel v-else-if="activeTab === 'printers'" />
            <MediaPanel v-else-if="activeTab === 'media'" />
            <PlaylistsPanel v-else-if="activeTab === 'playlists'" />
            <DisplaysPanel v-else-if="activeTab === 'displays'" />
            <AuditPanel v-else-if="activeTab === 'audit'" />
            <ReportsPanel v-else-if="activeTab === 'reports'" />
            </main>
        </div>
    </div>
</template>
