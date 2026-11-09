<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type {
    Category,
    Paginated,
    ReportExport,
    ReportFiltersPayload,
    ReportSummary,
    Section,
    Service,
    Station,
    Ticket,
} from '../../../shared/types/api';

const sections = ref<Section[]>([]);
const services = ref<Service[]>([]);
const stations = ref<Station[]>([]);

const summary = ref<ReportSummary | null>(null);
const tickets = ref<Paginated<Ticket> | null>(null);
const exportsList = ref<ReportExport[]>([]);

const filters = reactive({
    from: isoDate(daysAgo(29)),
    to: isoDate(new Date()),
    section: '',
    category: '',
    service: '',
    station: '',
});

const page = ref(1);
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    await Promise.all([loadCatalogs(), load()]);
});

async function loadCatalogs(): Promise<void> {
    try {
        const [sectionsResponse, servicesResponse, stationsResponse] = await Promise.all([
            api.sections(),
            api.services(),
            api.adminStations(),
        ]);

        sections.value = sectionsResponse.data;
        services.value = servicesResponse.data;
        stations.value = stationsResponse.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function categories(): Category[] {
    const unique = new Map<string, Category>();

    for (const section of sections.value) {
        for (const code of section.codes) {
            for (const category of code.categories) {
                if (!unique.has(category.slug)) {
                    unique.set(category.slug, category);
                }
            }
        }
    }

    return [...unique.values()];
}

function visibleServices(): Service[] {
    return filters.section === ''
        ? services.value
        : services.value.filter((service) => service.section?.slug === filters.section);
}

function visibleStations(): Station[] {
    return filters.section === ''
        ? stations.value
        : stations.value.filter((station) => station.section?.slug === filters.section);
}

async function load(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload = filterPayload();

    try {
        const [summaryResponse, ticketsResponse, exportsResponse] = await Promise.all([
            api.reportSummary(payload),
            api.reportTickets({ ...payload, page: page.value, per_page: 15 }),
            api.reportExports(),
        ]);

        summary.value = summaryResponse.data;
        tickets.value = ticketsResponse;
        exportsList.value = exportsResponse.data;
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
    if (!tickets.value) {
        return;
    }

    page.value = Math.min(Math.max(target, 1), tickets.value.meta.last_page);
    await load();
}

async function requestExport(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.createReportExport(filterPayload());
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function removeExport(exported: ReportExport): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deleteReportExport(exported.id);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function filterPayload(): ReportFiltersPayload {
    return {
        from: filters.from || undefined,
        to: filters.to || undefined,
        section: filters.section || undefined,
        category: filters.category || undefined,
        service: filters.service || undefined,
        station: filters.station || undefined,
    };
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const total = Math.round(seconds);
    const minutes = Math.floor(total / 60);
    const rest = total % 60;

    return minutes > 0 ? `${minutes}m ${rest}s` : `${total}s`;
}

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('es-BO') : '—';
}

function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    return bytes >= 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${bytes} B`;
}

function statusClass(status: ReportExport['status']): string {
    return {
        queued: 'bg-amber-100 text-amber-800',
        processing: 'bg-brand-50 text-brand-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
    }[status];
}

function serviceLabel(service: { name: string | null; label: string | null }): string {
    return service.name ?? service.label ?? 'Sin servicio';
}

function isoDate(date: Date): string {
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

function daysAgo(days: number): Date {
    const date = new Date();
    date.setDate(date.getDate() - days);

    return date;
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 space-y-6">
        <form class="rounded-3xl border border-slate-200 bg-white p-6" @submit.prevent="applyFilters">
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Desde
                    <input v-model="filters.from" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Hasta
                    <input v-model="filters.to" type="date" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Sección
                    <select v-model="filters.section" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3" @change="filters.station = ''; filters.service = ''">
                        <option value="">Todas</option>
                        <option v-for="section in sections" :key="section.slug" :value="section.slug">{{ section.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Categoría
                    <select v-model="filters.category" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        <option value="">Todas</option>
                        <option v-for="category in categories()" :key="category.slug" :value="category.slug">{{ category.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Servicio
                    <select v-model="filters.service" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        <option value="">Todos</option>
                        <option v-for="service in visibleServices()" :key="service.id" :value="service.slug">{{ service.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-700">
                    Ventanilla
                    <select v-model="filters.station" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-3">
                        <option value="">Todas</option>
                        <option v-for="station in visibleStations()" :key="station.id" :value="station.name">{{ station.name }}</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit" class="rounded-xl bg-brand-700 px-5 py-3 font-semibold text-white disabled:opacity-50" :disabled="busy">
                    Aplicar filtros
                </button>
                <button type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-800 disabled:opacity-50" :disabled="busy" @click="requestExport">
                    Exportar CSV
                </button>
            </div>
        </form>

        <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ message }}</p>
        <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">{{ error }}</p>

        <div v-if="summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Emitidos</p>
                <p class="mt-2 text-3xl font-semibold">{{ summary.totals.issued }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Finalizados</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ summary.totals.finished }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">No presentados</p>
                <p class="mt-2 text-3xl font-semibold text-amber-700">{{ summary.totals.no_show }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Expirados</p>
                <p class="mt-2 text-3xl font-semibold text-red-700">{{ summary.totals.expired }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Espera promedio</p>
                <p class="mt-2 text-3xl font-semibold">{{ formatDuration(summary.totals.avg_wait_seconds) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Atención promedio</p>
                <p class="mt-2 text-3xl font-semibold">{{ formatDuration(summary.totals.avg_service_seconds) }}</p>
            </article>
        </div>

        <div v-if="summary" class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Por sección</h3>
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2">Sección</th>
                            <th class="py-2">Emitidos</th>
                            <th class="py-2">Finalizados</th>
                            <th class="py-2">No presentados</th>
                            <th class="py-2">Espera</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in summary.by_section" :key="row.section.id">
                            <td class="py-2 text-slate-800">{{ row.section.name }}</td>
                            <td class="py-2">{{ row.issued }}</td>
                            <td class="py-2">{{ row.finished }}</td>
                            <td class="py-2">{{ row.no_show }}</td>
                            <td class="py-2">{{ formatDuration(row.avg_wait_seconds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Por categoría</h3>
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2">Categoría</th>
                            <th class="py-2">Emitidos</th>
                            <th class="py-2">Finalizados</th>
                            <th class="py-2">No presentados</th>
                            <th class="py-2">Espera</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in summary.by_category" :key="row.category.id">
                            <td class="py-2 text-slate-800">{{ row.category.name }}</td>
                            <td class="py-2">{{ row.issued }}</td>
                            <td class="py-2">{{ row.finished }}</td>
                            <td class="py-2">{{ row.no_show }}</td>
                            <td class="py-2">{{ formatDuration(row.avg_wait_seconds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Por servicio</h3>
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2">Servicio</th>
                            <th class="py-2">Emitidos</th>
                            <th class="py-2">Finalizados</th>
                            <th class="py-2">No presentados</th>
                            <th class="py-2">Espera</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in summary.by_service" :key="row.service.id ?? 'none'">
                            <td class="py-2 text-slate-800">{{ serviceLabel(row.service) }}</td>
                            <td class="py-2">{{ row.issued }}</td>
                            <td class="py-2">{{ row.finished }}</td>
                            <td class="py-2">{{ row.no_show }}</td>
                            <td class="py-2">{{ formatDuration(row.avg_wait_seconds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6">
                <h3 class="font-semibold">Por ventanilla</h3>
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2">Ventanilla</th>
                            <th class="py-2">Emitidos</th>
                            <th class="py-2">Finalizados</th>
                            <th class="py-2">No presentados</th>
                            <th class="py-2">Espera</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in summary.by_station" :key="row.station_label ?? 'none'">
                            <td class="py-2 text-slate-800">{{ row.station_label ?? 'Sin ventanilla' }}</td>
                            <td class="py-2">{{ row.issued }}</td>
                            <td class="py-2">{{ row.finished }}</td>
                            <td class="py-2">{{ row.no_show }}</td>
                            <td class="py-2">{{ formatDuration(row.avg_wait_seconds) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>
        </div>

        <article class="overflow-x-auto rounded-3xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Ticket</th>
                        <th class="px-4 py-3">Sección</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Servicio</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Ventanilla</th>
                        <th class="px-4 py-3">Emitido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="ticket in tickets?.data ?? []" :key="ticket.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-brand-700">{{ ticket.label }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ticket.section?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ticket.category?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ticket.service?.name ?? ticket.service_label ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ticket.status_label }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ticket.station_label ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ formatDateTime(ticket.issued_at) }}</td>
                    </tr>
                    <tr v-if="(tickets?.data.length ?? 0) === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">Sin tickets para los filtros seleccionados.</td>
                    </tr>
                </tbody>
            </table>
        </article>

        <div v-if="tickets" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                Página {{ tickets.meta.current_page }} de {{ tickets.meta.last_page }} · {{ tickets.meta.total }} tickets
            </p>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:opacity-40" :disabled="busy || tickets.meta.current_page <= 1" @click="goTo(tickets.meta.current_page - 1)">Anterior</button>
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:opacity-40" :disabled="busy || tickets.meta.current_page >= tickets.meta.last_page" @click="goTo(tickets.meta.current_page + 1)">Siguiente</button>
            </div>
        </div>

        <article class="rounded-3xl border border-slate-200 bg-white p-6">
            <h3 class="font-semibold">Exportaciones</h3>
            <p class="mt-1 text-sm text-slate-500">El CSV se genera en segundo plano; actualiza la lista para ver el resultado.</p>

            <div class="mt-4 space-y-3">
                <div v-for="exported in exportsList" :key="exported.id" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">Exportación #{{ exported.id }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ exported.filters.from }} → {{ exported.filters.to }}
                                · {{ exported.rows }} filas · {{ formatSize(exported.file_size) }}
                                · {{ exported.user?.name ?? 'Sistema' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(exported.created_at) }}</p>
                            <p v-if="exported.error" class="mt-1 text-xs text-red-700">{{ exported.error }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(exported.status)">
                            {{ exported.status_label }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a v-if="exported.download_url" :href="exported.download_url" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-brand-700 hover:border-brand-500">
                            Descargar
                        </a>
                        <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:border-red-400" :disabled="busy" @click="removeExport(exported)">
                            Eliminar
                        </button>
                    </div>
                </div>

                <p v-if="exportsList.length === 0" class="text-sm text-slate-500">Aún no hay exportaciones.</p>
            </div>
        </article>
    </section>
</template>
