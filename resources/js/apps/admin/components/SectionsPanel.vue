<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AdminSection } from '../../../shared/types/api';

const CATEGORIES: Array<{ slug: string; name: string }> = [
    { slug: 'general', name: 'Atención general' },
    { slug: 'pregnancy', name: 'Mujeres embarazadas' },
    { slug: 'elderly', name: 'Tercera edad' },
    { slug: 'disability', name: 'Discapacidad' },
];

const sections = ref<AdminSection[]>([]);
const editingSlug = ref<string | null>(null);
const forms = ref<Record<string, { name: string; is_active: boolean; sort_order: number }>>({});
const matrix = ref<Record<string, Record<string, string[]>>>({});
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(load);

async function load(): Promise<void> {
    try {
        const response = await api.adminSections();
        sections.value = response.data;

        for (const section of response.data) {
            forms.value[section.slug] = {
                name: section.name,
                is_active: section.is_active,
                sort_order: section.sort_order,
            };
            matrix.value[section.slug] = Object.fromEntries(
                section.codes.map((code) => [code.code, code.categories.map((category) => category.slug)]),
            );
        }
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function saveSection(section: AdminSection): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.updateAdminSection(section.slug, forms.value[section.slug]);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function saveMatrix(section: AdminSection): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.updateSectionMatrix(section.slug, {
            codes: Object.entries(matrix.value[section.slug]).map(([code, categories]) => ({
                code,
                categories,
            })),
        });

        message.value = response.message;
        editingSlug.value = null;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function toggleCategory(sectionSlug: string, code: string, categorySlug: string): void {
    const selected = matrix.value[sectionSlug][code];

    matrix.value[sectionSlug][code] = selected.includes(categorySlug)
        ? selected.filter((slug) => slug !== categorySlug)
        : [...selected, categorySlug];
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 space-y-6">
        <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ message }}</p>
        <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ error }}</p>

        <article v-for="section in sections" :key="section.id" class="rounded-3xl border border-slate-200 bg-white p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">{{ section.name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        <code class="text-brand-700">{{ section.slug }}</code>
                        · {{ section.stations_count ?? 0 }} ventanillas
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Matriz confirmada:
                        {{ section.matrix_confirmed_at ? `${section.matrix_confirmed_by ?? '—'} · ${new Date(section.matrix_confirmed_at).toLocaleString('es-BO')}` : 'sin confirmar' }}
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="section.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                    {{ section.is_active ? 'Activa' : 'Inactiva' }}
                </span>
            </div>

            <form class="mt-6 grid gap-4 md:grid-cols-[1fr_120px_140px_auto]" @submit.prevent="saveSection(section)">
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Nombre
                    <input v-model="forms[section.slug].name" required maxlength="80" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Orden
                    <input v-model.number="forms[section.slug].sort_order" type="number" min="0" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>
                <label class="flex items-end gap-3 pb-3 text-sm text-slate-700">
                    <input v-model="forms[section.slug].is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-slate-50">
                    Activa
                </label>
                <button type="submit" class="self-end rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    Guardar
                </button>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Matriz de códigos</h3>
                        <p class="text-sm text-slate-500">Asigne cada categoría al código que le corresponde.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:border-brand-500" @click="editingSlug = editingSlug === section.slug ? null : section.slug">
                        {{ editingSlug === section.slug ? 'Cerrar' : 'Editar matriz' }}
                    </button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div v-for="code in section.codes" :key="code.code" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-lg font-semibold text-brand-700">{{ code.code }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ code.categories.map((category) => category.name).join(', ') || 'Sin categorías' }}</p>
                    </div>
                </div>

                <div v-if="editingSlug === section.slug" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div v-for="code in section.codes" :key="code.code" class="grid gap-3 border-b border-slate-200 py-3 last:border-0 md:grid-cols-[80px_1fr]">
                        <p class="text-lg font-semibold text-brand-700">{{ code.code }}</p>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="category in CATEGORIES" :key="category.slug" class="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 bg-white"
                                    :checked="matrix[section.slug][code.code].includes(category.slug)"
                                    @change="toggleCategory(section.slug, code.code, category.slug)"
                                >
                                {{ category.name }}
                            </label>
                        </div>
                    </div>

                    <button type="button" class="mt-4 rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy" @click="saveMatrix(section)">
                        Guardar matriz
                    </button>
                </div>
            </div>
        </article>
    </section>
</template>
