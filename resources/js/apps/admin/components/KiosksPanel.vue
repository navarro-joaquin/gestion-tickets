<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AdminSection, KioskDevice } from '../../../shared/types/api';

interface KioskForm {
    id: number | null;
    name: string;
    slug: string;
    is_active: boolean;
    sections: string[];
}

const devices = ref<KioskDevice[]>([]);
const sections = ref<AdminSection[]>([]);
const form = ref<KioskForm>(emptyForm());
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    await loadSections();
    await load();
});

async function loadSections(): Promise<void> {
    try {
        const response = await api.adminSections();
        sections.value = response.data.filter((section) => section.is_active);
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function load(): Promise<void> {
    try {
        const response = await api.adminKiosks();
        devices.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function edit(device: KioskDevice): void {
    form.value = {
        id: device.id,
        name: device.name,
        slug: device.slug,
        is_active: device.is_active,
        sections: device.sections.map((section) => section.slug),
    };
}

function reset(): void {
    form.value = emptyForm();
}

function toggleSection(slug: string): void {
    form.value.sections = form.value.sections.includes(slug)
        ? form.value.sections.filter((value) => value !== slug)
        : [...form.value.sections, slug];
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const usingEdit = form.value.id !== null;
        const response = usingEdit
            ? await api.updateKiosk(form.value.id as number, {
                name: form.value.name,
                slug: form.value.slug || null,
                is_active: form.value.is_active,
                sections: form.value.sections,
            })
            : await api.createKiosk({
                name: form.value.name,
                slug: form.value.slug || null,
                is_active: form.value.is_active,
                sections: form.value.sections,
            });

        message.value = response.message;
        reset();
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(device: KioskDevice): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteKiosk(device.id);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function kioskUrl(token: string): string {
    return `${window.location.origin}/quiosco?quiosco=${encodeURIComponent(token)}`;
}

async function copyUrl(token: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(kioskUrl(token));
        message.value = 'Enlace del quiosco copiado.';
    } catch {
        error.value = 'No se pudo copiar el enlace.';
    }
}

function emptyForm(): KioskForm {
    return { id: null, name: '', slug: '', is_active: true, sections: [] };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-800 bg-slate-900 p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar quiosco' : 'Nuevo quiosco' }}</h2>
            <p class="mt-2 text-sm text-slate-400">Cada quiosco habilita solo las secciones marcadas. Abre la URL con el token del dispositivo.</p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Nombre
                    <input v-model="form.name" required maxlength="80" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Slug (opcional)
                    <input v-model="form.slug" maxlength="80" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <div>
                    <p class="text-sm text-slate-300">Secciones habilitadas</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <label v-for="section in sections" :key="section.slug" class="flex items-center gap-2 text-sm text-slate-300">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-700 bg-slate-950"
                                :checked="form.sections.includes(section.slug)"
                                @change="toggleSection(section.slug)"
                            >
                            {{ section.name }}
                        </label>
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950">
                    Quiosco activo
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear quiosco' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-300" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">{{ error }}</p>

            <article v-for="device in devices" :key="device.id" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ device.name }}</h3>
                        <p class="text-sm text-slate-400">{{ device.sections.map((section) => section.name).join(' · ') || 'Sin secciones' }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Último acceso: {{ device.last_seen_at ? new Date(device.last_seen_at).toLocaleString('es-BO') : 'nunca' }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="device.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400'">
                        {{ device.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <p class="mt-3 break-all rounded-xl bg-slate-950 px-3 py-2 font-mono text-xs text-sky-300">{{ kioskUrl(device.token) }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-sky-500" @click="copyUrl(device.token)">Copiar enlace</button>
                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-sky-500" @click="edit(device)">Editar</button>
                    <button type="button" class="rounded-lg border border-red-500/40 px-3 py-2 text-sm text-red-200 hover:border-red-400" @click="remove(device)">Eliminar</button>
                </div>
            </article>
        </div>
    </section>
</template>
