<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AdminSection, DisplayDevice, DisplayLayout, Playlist } from '../../../shared/types/api';

interface DisplayForm {
    id: number | null;
    name: string;
    slug: string;
    layout: DisplayLayout;
    is_active: boolean;
    sections: string[];
    playlists: number[];
}

const displays = ref<DisplayDevice[]>([]);
const sections = ref<AdminSection[]>([]);
const playlists = ref<Playlist[]>([]);
const form = ref<DisplayForm>(emptyForm());
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    await Promise.all([loadSections(), loadPlaylists(), load()]);
});

async function loadSections(): Promise<void> {
    try {
        const response = await api.adminSections();
        sections.value = response.data.filter((section) => section.is_active);
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadPlaylists(): Promise<void> {
    try {
        const response = await api.playlists();
        playlists.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function load(): Promise<void> {
    try {
        const response = await api.displays();
        displays.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function edit(display: DisplayDevice): void {
    form.value = {
        id: display.id,
        name: display.name,
        slug: display.slug,
        layout: display.layout,
        is_active: display.is_active,
        sections: display.sections
            .filter((section) => section.is_active)
            .map((section) => section.slug),
        playlists: display.playlists.map((playlist) => playlist.id),
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

function togglePlaylist(id: number): void {
    form.value.playlists = form.value.playlists.includes(id)
        ? form.value.playlists.filter((value) => value !== id)
        : [...form.value.playlists, id];
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const payload = {
            name: form.value.name,
            slug: form.value.slug || null,
            layout: form.value.layout,
            is_active: form.value.is_active,
            sections: form.value.sections,
            playlists: form.value.playlists,
        };

        const response = form.value.id === null
            ? await api.createDisplay(payload)
            : await api.updateDisplay(form.value.id, payload);

        message.value = response.message;
        reset();
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(display: DisplayDevice): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteDisplay(display.id);
        message.value = response.message;

        if (form.value.id === display.id) {
            reset();
        }

        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function rotateToken(display: DisplayDevice): Promise<void> {
    if (busy.value || !window.confirm('El enlace actual dejará de funcionar. ¿Regenerar el token?')) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.rotateDisplayToken(display.id);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function displayUrl(token: string): string {
    return `${window.location.origin}/pantalla?pantalla=${encodeURIComponent(token)}`;
}

async function copyUrl(token: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(displayUrl(token));
        message.value = 'Enlace de la pantalla copiado.';
    } catch {
        error.value = 'No se pudo copiar el enlace.';
    }
}

function layoutLabel(layout: DisplayLayout): string {
    return {
        queue: 'Solo llamados',
        mixed: 'Llamados + multimedia',
        media: 'Solo multimedia',
    }[layout];
}

function emptyForm(): DisplayForm {
    return {
        id: null,
        name: '',
        slug: '',
        layout: 'mixed',
        is_active: true,
        sections: [],
        playlists: [],
    };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-200 bg-white p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar pantalla' : 'Nueva pantalla' }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Cada pantalla recibe un token, muestra solo sus secciones y reproduce sus playlists.
            </p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Nombre
                    <input v-model="form.name" required maxlength="80" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Slug (opcional)
                    <input v-model="form.slug" maxlength="80" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Diseño
                    <select v-model="form.layout" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        <option value="queue">Solo llamados</option>
                        <option value="mixed">Llamados + multimedia</option>
                        <option value="media">Solo multimedia</option>
                    </select>
                </label>

                <div>
                    <p class="text-sm text-slate-700">Secciones visibles</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <label v-for="section in sections" :key="section.slug" class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 bg-slate-50"
                                :checked="form.sections.includes(section.slug)"
                                @change="toggleSection(section.slug)"
                            >
                            {{ section.name }}
                        </label>
                    </div>
                </div>

                <div>
                    <p class="text-sm text-slate-700">Playlists</p>
                    <div v-if="playlists.length > 0" class="mt-2 flex flex-wrap gap-4">
                        <label v-for="playlist in playlists" :key="playlist.id" class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 bg-slate-50"
                                :checked="form.playlists.includes(playlist.id)"
                                @change="togglePlaylist(playlist.id)"
                            >
                            {{ playlist.name }}
                        </label>
                    </div>
                    <p v-else class="mt-2 text-sm text-slate-500">Crea una playlist para asignarla a la pantalla.</p>
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                    Pantalla activa
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear pantalla' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ error }}</p>

            <article v-for="display in displays" :key="display.id" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ display.name }}</h3>
                        <p class="text-sm text-slate-500">
                            {{ display.sections.map((section) => section.name).join(' · ') || 'Sin secciones' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ layoutLabel(display.layout) }} ·
                            {{ display.playlists.map((playlist) => playlist.name).join(' · ') || 'Sin playlists' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Último acceso: {{ display.last_seen_at ? new Date(display.last_seen_at).toLocaleString('es-BO') : 'nunca' }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="display.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                        {{ display.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>

                <p class="mt-3 break-all rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-brand-700">{{ displayUrl(display.token) }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="copyUrl(display.token)">Copiar enlace</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="edit(display)">Editar</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" :disabled="busy" @click="rotateToken(display)">Regenerar token</button>
                    <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" :disabled="busy" @click="remove(display)">Eliminar</button>
                </div>
            </article>

            <p v-if="displays.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                Aún no hay pantallas configuradas.
            </p>
        </div>
    </section>
</template>
