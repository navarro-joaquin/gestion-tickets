<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '../../shared/api/client';
import type { AdminSettings } from '../../shared/types/api';

const settings = ref<AdminSettings | null>(null);
const closingTime = ref('16:30');
const message = ref('');
const error = ref('');
const busy = ref(false);

const nextClosingLabel = computed<string>(() => {
    if (!settings.value) {
        return '';
    }

    const date = new Date(settings.value.next_closing_at);

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(date);
});

onMounted(loadSettings);

async function loadSettings(): Promise<void> {
    try {
        const response = await api.adminSettings();
        settings.value = response.data;
        closingTime.value = response.data.closing_time;
        error.value = '';
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.updateAdminSettings(closingTime.value);
        settings.value = response.data;
        closingTime.value = response.data.closing_time;
        message.value = response.message;
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-6 py-10 text-white">
        <div class="mx-auto max-w-3xl">
            <header class="border-b border-slate-800 pb-6">
                <p class="text-sm uppercase tracking-[0.3em] text-sky-400">Gestión de tickets</p>
                <h1 class="text-3xl font-semibold">Administración · Ajustes</h1>
                <p class="mt-2 text-slate-400">
                    Configuración global de la operación.
                </p>
            </header>

            <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8">
                <h2 class="text-xl font-semibold">Cierre de jornada</h2>
                <p class="mt-2 text-sm text-slate-400">
                    A esta hora se expiran los tickets en espera, se marcan como no presentados
                    los llamados sin atención y se finalizan los que estaban en atención.
                </p>

                <div class="mt-8 flex flex-wrap items-end gap-4">
                    <label class="flex flex-col gap-2 text-sm text-slate-300">
                        Hora de cierre
                        <input
                            v-model="closingTime"
                            type="time"
                            step="60"
                            class="w-40 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-lg text-slate-100"
                        >
                    </label>

                    <button
                        type="button"
                        class="rounded-xl bg-sky-500 px-6 py-3 font-semibold text-slate-950 transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="save"
                    >
                        {{ busy ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>

                <dl class="mt-8 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <dt class="text-slate-400">Zona horaria</dt>
                        <dd class="mt-1 text-slate-200">{{ settings?.timezone ?? '—' }}</dd>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <dt class="text-slate-400">Próximo cierre</dt>
                        <dd class="mt-1 text-slate-200">{{ nextClosingLabel || '—' }}</dd>
                    </div>
                </dl>

                <p v-if="message" class="mt-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">
                    {{ message }}
                </p>
                <p v-if="error" class="mt-6 rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">
                    {{ error }}
                </p>
            </section>

            <p class="mt-6 text-sm text-slate-500">
                La autenticación y los permisos de administración se habilitan en la Fase 4.
                Mientras tanto, este ajuste debe usarse solo en el entorno local.
            </p>
        </div>
    </main>
</template>
