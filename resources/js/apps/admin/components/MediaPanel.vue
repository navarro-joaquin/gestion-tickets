<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { MediaAsset } from '../../../shared/types/api';

interface MediaForm {
    id: number | null;
    name: string;
    type: 'image' | 'video';
    duration_seconds: string;
    is_active: boolean;
    file: File | null;
}

const assets = ref<MediaAsset[]>([]);
const form = ref<MediaForm>(emptyForm());
const fileInput = ref<HTMLInputElement | null>(null);
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(load);

async function load(): Promise<void> {
    try {
        const response = await api.mediaAssets();
        assets.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function edit(asset: MediaAsset): void {
    form.value = {
        id: asset.id,
        name: asset.name,
        type: asset.type,
        duration_seconds: asset.duration_seconds === null ? '' : String(asset.duration_seconds),
        is_active: asset.is_active,
        file: null,
    };
}

function reset(): void {
    form.value = emptyForm();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.value.file = input.files?.[0] ?? null;
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        if (form.value.id === null) {
            if (!form.value.file) {
                throw new Error('Selecciona un archivo de imagen o video.');
            }

            const payload = new FormData();
            payload.append('name', form.value.name);
            payload.append('type', form.value.type);
            payload.append('is_active', form.value.is_active ? '1' : '0');

            if (form.value.duration_seconds !== '') {
                payload.append('duration_seconds', form.value.duration_seconds);
            }

            payload.append('file', form.value.file);

            const response = await api.createMediaAsset(payload);
            message.value = response.message;
        } else {
            const response = await api.updateMediaAsset(form.value.id, {
                name: form.value.name,
                type: form.value.type,
                duration_seconds: form.value.duration_seconds === ''
                    ? null
                    : Number(form.value.duration_seconds),
                is_active: form.value.is_active,
            });
            message.value = response.message;
        }

        reset();
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(asset: MediaAsset): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteMediaAsset(asset.id);
        message.value = response.message;

        if (form.value.id === asset.id) {
            reset();
        }

        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function formatSize(bytes: number): string {
    return bytes >= 1_048_576
        ? `${(bytes / 1_048_576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function emptyForm(): MediaForm {
    return {
        id: null,
        name: '',
        type: 'image',
        duration_seconds: '',
        is_active: true,
        file: null,
    };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-200 bg-white p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar contenido' : 'Nuevo contenido' }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                Imágenes JPG, PNG, WEBP o GIF y videos MP4 o WEBM. El archivo se guarda en el almacenamiento público.
            </p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Nombre
                    <input v-model="form.name" required maxlength="120" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Tipo
                    <select v-model="form.type" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        <option value="image">Imagen</option>
                        <option value="video">Video</option>
                    </select>
                </label>

                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Duración en pantalla (segundos, opcional)
                    <input
                        v-model="form.duration_seconds"
                        type="number"
                        min="1"
                        max="3600"
                        placeholder="10 por defecto"
                        class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3"
                    >
                </label>

                <label v-if="form.id === null" class="flex flex-col gap-2 text-sm text-slate-700">
                    Archivo
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"
                        required
                        class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3"
                        @change="onFileChange"
                    >
                </label>

                <p v-else class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    El archivo no se reemplaza al editar; crea un contenido nuevo si necesitas cambiar el material.
                </p>

                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                    Contenido activo
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Subir contenido' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ error }}</p>

            <article v-for="asset in assets" :key="asset.id" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap gap-4">
                    <div class="h-28 w-44 overflow-hidden rounded-xl bg-slate-50">
                        <img v-if="asset.type === 'image'" :src="asset.url" :alt="asset.name" class="h-full w-full object-cover">
                        <video v-else :src="asset.url" class="h-full w-full object-cover" muted preload="metadata" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ asset.name }}</h3>
                                <p class="text-sm text-slate-500">
                                    {{ asset.type === 'image' ? 'Imagen' : 'Video' }} ·
                                    {{ formatSize(asset.file_size) }} ·
                                    {{ asset.duration_seconds ?? 10 }} s
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="asset.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                                {{ asset.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-brand-500" @click="edit(asset)">Editar</button>
                            <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" :disabled="busy" @click="remove(asset)">Eliminar</button>
                        </div>
                    </div>
                </div>
            </article>

            <p v-if="assets.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                Aún no hay contenidos multimedia.
            </p>
        </div>
    </section>
</template>
