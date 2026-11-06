<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { MediaAsset, Playlist } from '../../../shared/types/api';

interface PlaylistFormItem {
    media_asset_id: number;
    duration_seconds: string;
    is_active: boolean;
}

interface PlaylistForm {
    id: number | null;
    name: string;
    description: string;
    is_active: boolean;
    items: PlaylistFormItem[];
}

const playlists = ref<Playlist[]>([]);
const media = ref<MediaAsset[]>([]);
const form = ref<PlaylistForm>(emptyForm());
const selectedMediaId = ref<number | null>(null);
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    await Promise.all([loadPlaylists(), loadMedia()]);
});

async function loadPlaylists(): Promise<void> {
    try {
        const response = await api.playlists();
        playlists.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function loadMedia(): Promise<void> {
    try {
        const response = await api.mediaAssets();
        media.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function availableMedia(): MediaAsset[] {
    const used = new Set(form.value.items.map((item) => item.media_asset_id));

    return media.value.filter((asset) => !used.has(asset.id));
}

function mediaName(id: number): string {
    return media.value.find((asset) => asset.id === id)?.name ?? `Contenido #${id}`;
}

function addItem(): void {
    if (selectedMediaId.value === null) {
        return;
    }

    form.value.items.push({
        media_asset_id: selectedMediaId.value,
        duration_seconds: '',
        is_active: true,
    });

    selectedMediaId.value = null;
}

function removeItem(position: number): void {
    form.value.items.splice(position, 1);
}

function moveItem(position: number, offset: number): void {
    const target = position + offset;

    if (target < 0 || target >= form.value.items.length) {
        return;
    }

    const [item] = form.value.items.splice(position, 1);
    form.value.items.splice(target, 0, item);
}

function edit(playlist: Playlist): void {
    form.value = {
        id: playlist.id,
        name: playlist.name,
        description: playlist.description ?? '',
        is_active: playlist.is_active,
        items: playlist.items
            .filter((item) => item.media !== null)
            .map((item) => ({
                media_asset_id: item.media?.id as number,
                duration_seconds: item.duration_seconds === null ? '' : String(item.duration_seconds),
                is_active: item.is_active,
            })),
    };
}

function reset(): void {
    form.value = emptyForm();
    selectedMediaId.value = null;
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
            description: form.value.description || null,
            is_active: form.value.is_active,
            items: form.value.items.map((item) => ({
                media_asset_id: item.media_asset_id,
                duration_seconds: item.duration_seconds === '' ? null : Number(item.duration_seconds),
                is_active: item.is_active,
            })),
        };

        const response = form.value.id === null
            ? await api.createPlaylist(payload)
            : await api.updatePlaylist(form.value.id, payload);

        message.value = response.message;
        reset();
        await loadPlaylists();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(playlist: Playlist): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deletePlaylist(playlist.id);
        message.value = response.message;

        if (form.value.id === playlist.id) {
            reset();
        }

        await loadPlaylists();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function emptyForm(): PlaylistForm {
    return { id: null, name: '', description: '', is_active: true, items: [] };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-800 bg-slate-900 p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar playlist' : 'Nueva playlist' }}</h2>
            <p class="mt-2 text-sm text-slate-400">
                El orden de la lista es el orden de reproducción en las pantallas.
            </p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Nombre
                    <input v-model="form.name" required maxlength="80" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Descripción
                    <textarea v-model="form.description" rows="2" maxlength="500" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" />
                </label>

                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950">
                    Playlist activa
                </label>

                <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                    <p class="text-sm font-medium text-slate-300">Contenidos</p>

                    <div class="mt-3 flex gap-2">
                        <select v-model="selectedMediaId" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                            <option :value="null">Selecciona un contenido</option>
                            <option v-for="asset in availableMedia()" :key="asset.id" :value="asset.id">
                                {{ asset.name }} ({{ asset.type === 'image' ? 'imagen' : 'video' }})
                            </option>
                        </select>
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-200" @click="addItem">
                            Añadir
                        </button>
                    </div>

                    <div v-if="form.items.length > 0" class="mt-4 space-y-2">
                        <div
                            v-for="(item, position) in form.items"
                            :key="item.media_asset_id"
                            class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-900 px-3 py-2"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ mediaName(item.media_asset_id) }}</span>
                            <input
                                v-model="item.duration_seconds"
                                type="number"
                                min="1"
                                max="3600"
                                placeholder="10 s"
                                class="w-20 rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-sm"
                            >
                            <label class="flex items-center gap-1 text-xs text-slate-400">
                                <input v-model="item.is_active" type="checkbox" class="h-3 w-3 rounded border-slate-700 bg-slate-950">
                                Activo
                            </label>
                            <button type="button" class="rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300" :disabled="position === 0" @click="moveItem(position, -1)">↑</button>
                            <button type="button" class="rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300" :disabled="position === form.items.length - 1" @click="moveItem(position, 1)">↓</button>
                            <button type="button" class="rounded-lg border border-red-500/40 px-2 py-1 text-xs text-red-200" @click="removeItem(position)">Quitar</button>
                        </div>
                    </div>

                    <p v-else class="mt-4 text-sm text-slate-500">La playlist está vacía.</p>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear playlist' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-300" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">{{ error }}</p>

            <article v-for="playlist in playlists" :key="playlist.id" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ playlist.name }}</h3>
                        <p class="text-sm text-slate-400">{{ playlist.description || 'Sin descripción' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ playlist.items.length }} contenidos</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="playlist.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400'">
                        {{ playlist.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-500">
                    {{ playlist.items.map((item) => item.media?.name ?? 'Contenido eliminado').join(' · ') || 'Sin contenidos' }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-sky-500" @click="edit(playlist)">Editar</button>
                    <button type="button" class="rounded-lg border border-red-500/40 px-3 py-2 text-sm text-red-200 hover:border-red-400" :disabled="busy" @click="remove(playlist)">Eliminar</button>
                </div>
            </article>

            <p v-if="playlists.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-6 text-sm text-slate-500">
                Aún no hay playlists.
            </p>
        </div>
    </section>
</template>
