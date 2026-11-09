<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AdminSection, AdminUser, Station } from '../../../shared/types/api';

interface UserForm {
    id: number | null;
    name: string;
    email: string;
    password: string;
    role: string;
    is_active: boolean;
    default_section: string;
    default_station_id: number | null;
}

const ROLES = [
    { value: 'admin', label: 'Administrador' },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'operator', label: 'Operador' },
];

const users = ref<AdminUser[]>([]);
const sections = ref<AdminSection[]>([]);
const stations = ref<Station[]>([]);
const form = ref<UserForm>(emptyForm());
const message = ref('');
const error = ref('');
const busy = ref(false);

const requiresDefaultAssignment = computed<boolean>(
    () => form.value.role === 'operator' || form.value.role === 'supervisor',
);

const availableDefaultStations = computed<Station[]>(() =>
    stations.value.filter(
        (station) => station.is_active && station.section?.slug === form.value.default_section,
    ),
);

onMounted(async () => {
    await Promise.all([loadSections(), loadStations()]);
    reset();
    await load();
});

async function load(): Promise<void> {
    try {
        const response = await api.adminUsers();
        users.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadSections(): Promise<void> {
    try {
        const response = await api.adminSections();
        sections.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadStations(): Promise<void> {
    try {
        const response = await api.adminStations();
        stations.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function edit(user: AdminUser): void {
    const fallbackSection = user.default_section?.slug ?? sections.value[0]?.slug ?? '';
    const fallbackStation = stations.value.find(
        (station) => station.is_active && station.section?.slug === fallbackSection,
    );
    const keepsCurrentStation = stations.value.some(
        (station) => station.is_active
            && station.id === user.default_station?.id
            && station.section?.slug === fallbackSection,
    );

    form.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
        is_active: user.is_active,
        default_section: fallbackSection,
        default_station_id: keepsCurrentStation
            ? user.default_station?.id ?? null
            : fallbackStation?.id ?? null,
    };
}

function reset(): void {
    form.value = emptyForm();
}

function onSectionChange(): void {
    form.value.default_station_id = availableDefaultStations.value[0]?.id ?? null;
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password.trim() === '' ? undefined : form.value.password,
        role: form.value.role,
        is_active: form.value.is_active,
        default_section: requiresDefaultAssignment.value ? form.value.default_section : null,
        default_station_id: requiresDefaultAssignment.value ? form.value.default_station_id : null,
    };

    try {
        const response = form.value.id
            ? await api.updateUser(form.value.id, payload)
            : await api.createUser(payload);

        message.value = response.message;
        reset();
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(user: AdminUser): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteUser(user.id);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function emptyForm(): UserForm {
    const section = sections.value[0]?.slug ?? '';
    const station = stations.value.find(
        (candidate) => candidate.is_active && candidate.section?.slug === section,
    );

    return {
        id: null,
        name: '',
        email: '',
        password: '',
        role: 'operator',
        is_active: true,
        default_section: section,
        default_station_id: station?.id ?? null,
    };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-800 bg-slate-900 p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
            <p class="mt-2 text-sm text-slate-400">La contraseña es obligatoria al crear; al editar, déjala vacía para conservarla.</p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Nombre
                    <input v-model="form.name" required maxlength="120" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Correo electrónico
                    <input v-model="form.email" type="email" required class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Contraseña
                    <input v-model="form.password" type="password" :required="form.id === null" minlength="8" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Rol
                    <select v-model="form.role" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                        <option v-for="role in ROLES" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                </label>

                <div v-if="requiresDefaultAssignment" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs text-slate-500">
                        Se preseleccionan al abrir la jornada. El operador puede cambiarlos manualmente cuando sea necesario.
                    </p>
                    <label class="flex flex-col gap-2 text-sm text-slate-300">
                        Sección por defecto
                        <select v-model="form.default_section" required class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" @change="onSectionChange">
                            <option v-for="section in sections" :key="section.slug" :value="section.slug">{{ section.name }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-2 text-sm text-slate-300">
                        Ventanilla por defecto
                        <select v-model="form.default_station_id" required class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                            <option :value="null" disabled>Seleccione</option>
                            <option v-for="station in availableDefaultStations" :key="station.id" :value="station.id">{{ station.name }}</option>
                        </select>
                    </label>
                    <p v-if="availableDefaultStations.length === 0" class="text-xs text-amber-300">
                        La sección seleccionada no tiene ventanillas activas.
                    </p>
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950">
                    Usuario activo
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-300" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">{{ error }}</p>

            <article v-for="user in users" :key="user.id" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ user.name }}</h3>
                        <p class="text-sm text-slate-400">{{ user.email }}</p>
                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ user.role_label }}</p>
                        <p v-if="user.role !== 'admin'" class="mt-2 text-sm text-slate-400">
                            {{ user.default_section?.name ?? 'Sin sección' }} · {{ user.default_station?.name ?? 'Sin ventanilla' }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="user.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400'">
                        {{ user.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-sky-500" @click="edit(user)">Editar</button>
                    <button type="button" class="rounded-lg border border-red-500/40 px-3 py-2 text-sm text-red-200 hover:border-red-400" @click="remove(user)">Eliminar</button>
                </div>
            </article>
        </div>
    </section>
</template>
