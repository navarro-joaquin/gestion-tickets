<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AdminSection, Station } from '../../../shared/types/api';

interface StationForm {
    id: number | null;
    section: string;
    name: string;
    is_active: boolean;
    sort_order: number;
}

const sections = ref<AdminSection[]>([]);
const stations = ref<Station[]>([]);
const filter = ref('');
const form = ref<StationForm>(emptyForm());
const message = ref('');
const error = ref('');
const busy = ref(false);

const visibleStations = computed<Station[]>(() =>
    filter.value === ''
        ? stations.value
        : stations.value.filter((station) => station.section?.slug === filter.value),
);

onMounted(async () => {
    await loadSections();
    await loadStations();
});

async function loadSections(): Promise<void> {
    try {
        const response = await api.adminSections();
        sections.value = response.data;
        form.value.section ||= response.data[0]?.slug ?? '';
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

function edit(station: Station): void {
    form.value = {
        id: station.id,
        section: station.section?.slug ?? '',
        name: station.name,
        is_active: station.is_active,
        sort_order: station.sort_order,
    };
}

function reset(): void {
    form.value = emptyForm(sections.value[0]?.slug ?? '');
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        section: form.value.section,
        name: form.value.name,
        is_active: form.value.is_active,
        sort_order: form.value.sort_order,
    };

    try {
        const response = form.value.id
            ? await api.updateStation(form.value.id, payload)
            : await api.createStation(payload);

        message.value = response.message;
        reset();
        await loadStations();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(station: Station): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteStation(station.id);
        message.value = response.message;
        await loadStations();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function emptyForm(section = ''): StationForm {
    return { id: null, section, name: '', is_active: true, sort_order: 0 };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-200 bg-white p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar ventanilla' : 'Nueva ventanilla' }}</h2>
            <p class="mt-2 text-sm text-slate-500">Cada ventanilla pertenece a una sección; la consola solo muestra las activas.</p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Sección
                    <select v-model="form.section" required class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3" :disabled="form.id !== null">
                        <option v-for="section in sections" :key="section.slug" :value="section.slug">{{ section.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Nombre
                    <input v-model="form.name" required maxlength="50" placeholder="Ventanilla 1" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-2 text-sm text-slate-700">
                        Orden
                        <input v-model.number="form.sort_order" type="number" min="0" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                    </label>
                    <label class="flex items-end gap-3 pb-3 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                        Activa
                    </label>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear ventanilla' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-4">
            <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ error }}</p>

            <label class="flex flex-col gap-2 text-sm text-slate-700">
                Filtrar por sección
                <select v-model="filter" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                    <option value="">Todas</option>
                    <option v-for="section in sections" :key="section.slug" :value="section.slug">{{ section.name }}</option>
                </select>
            </label>

            <article v-for="station in visibleStations" :key="station.id" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ station.name }}</h3>
                        <p class="text-sm text-slate-500">{{ station.section?.name ?? '—' }} · orden {{ station.sort_order }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="station.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                        {{ station.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="edit(station)">Editar</button>
                    <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" @click="remove(station)">Eliminar</button>
                </div>
            </article>
        </div>
    </section>
</template>
