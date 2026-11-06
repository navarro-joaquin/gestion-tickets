<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { AuditLogEntry, Paginated } from '../../../shared/types/api';

const logs = ref<Paginated<AuditLogEntry> | null>(null);
const action = ref('');
const entityType = ref('');
const page = ref(1);
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(load);

async function load(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        logs.value = await api.auditLogs({
            action: action.value || undefined,
            entity_type: entityType.value || undefined,
            page: page.value,
            per_page: 25,
        });
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function applyFilters(): Promise<void> {
    page.value = 1;
    await load();
}

async function goTo(target: number): Promise<void> {
    if (!logs.value) {
        return;
    }

    page.value = Math.min(Math.max(target, 1), logs.value.meta.last_page);
    await load();
}

function formatPayload(payload: Record<string, unknown> | null): string {
    return payload ? JSON.stringify(payload) : '—';
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 space-y-4">
        <form class="flex flex-wrap items-end gap-3 rounded-3xl border border-slate-800 bg-slate-900 p-6" @submit.prevent="applyFilters">
            <label class="flex flex-col gap-2 text-sm text-slate-300">
                Acción
                <input v-model="action" placeholder="section.updated" class="w-56 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
            </label>
            <label class="flex flex-col gap-2 text-sm text-slate-300">
                Entidad
                <input v-model="entityType" placeholder="App\Domain\Sections\Section" class="w-72 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
            </label>
            <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy">Filtrar</button>
            <button type="button" class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-300" @click="action = ''; entityType = ''; applyFilters()">Limpiar</button>
        </form>

        <p v-if="message" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">{{ message }}</p>
        <p v-if="error" class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">{{ error }}</p>

        <div class="overflow-x-auto rounded-3xl border border-slate-800 bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Entidad</th>
                        <th class="px-4 py-3">Antes</th>
                        <th class="px-4 py-3">Después</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <tr v-for="log in logs?.data ?? []" :key="log.id">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-300">{{ log.created_at ? new Date(log.created_at).toLocaleString('es-BO') : '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ log.user?.name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-sky-300">{{ log.action }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ log.entity_type }} #{{ log.entity_id ?? '—' }}</td>
                        <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-slate-500" :title="formatPayload(log.before)">{{ formatPayload(log.before) }}</td>
                        <td class="max-w-xs truncate px-4 py-3 font-mono text-xs text-slate-400" :title="formatPayload(log.after)">{{ formatPayload(log.after) }}</td>
                    </tr>
                    <tr v-if="(logs?.data.length ?? 0) === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Sin registros para los filtros seleccionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="logs" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-400">
                Página {{ logs.meta.current_page }} de {{ logs.meta.last_page }} · {{ logs.meta.total }} registros
            </p>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 disabled:opacity-40" :disabled="busy || logs.meta.current_page <= 1" @click="goTo(logs.meta.current_page - 1)">Anterior</button>
                <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 disabled:opacity-40" :disabled="busy || logs.meta.current_page >= logs.meta.last_page" @click="goTo(logs.meta.current_page + 1)">Siguiente</button>
            </div>
        </div>
    </section>
</template>
