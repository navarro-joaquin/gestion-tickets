<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../../shared/api/client';
import { createPrintDriver } from '../../shared/printing/escpos';
import type { Category, PublicKiosk, Section, ServiceSummary, Ticket } from '../../shared/types/api';

const sections = ref<Section[]>([]);
const selectedSection = ref<Section | null>(null);
const logoUrl = '/images/cessa-logo.jpg';
const ticket = ref<Ticket | null>(null);
const kioskToken = ref<string | null>(null);
const kioskPrinter = ref<PublicKiosk['printer']>(null);
const error = ref('');
const busy = ref(false);
const printState = ref<'idle' | 'printing' | 'printed' | 'failed'>('idle');

let resetTimer: ReturnType<typeof setTimeout> | undefined;

const categories = computed<Category[]>(() => {
    if (!selectedSection.value) {
        return [];
    }

    const unique = new Map<string, Category>();

    for (const code of selectedSection.value.codes) {
        for (const category of code.categories) {
            if (!unique.has(category.slug)) {
                unique.set(category.slug, category);
            }
        }
    }

    return [...unique.values()].sort(
        (left, right) => Number(left.is_preferential) - Number(right.is_preferential),
    );
});

const services = computed<ServiceSummary[]>(() => selectedSection.value?.services ?? []);

onMounted(async () => {
    await loadSections();
});

onBeforeUnmount(() => {
    if (resetTimer) {
        clearTimeout(resetTimer);
    }
});

async function loadSections(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('quiosco') ?? params.get('kiosk');

    if (!token) {
        error.value = 'Abre este quiosco con el enlace que incluye su token.';
        return;
    }

    try {
        kioskToken.value = token;
        const response = await api.kiosk(token);
        kioskPrinter.value = response.data.printer;
        sections.value = response.data.sections;
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'No se pudieron cargar las secciones.';
    }
}

function selectSection(section: Section): void {
    selectedSection.value = section;
    ticket.value = null;
    printState.value = 'idle';
    error.value = '';
}

function clearSection(): void {
    selectedSection.value = null;
    ticket.value = null;
    printState.value = 'idle';
    error.value = '';
}

async function issueCategory(category: Category): Promise<void> {
    if (!selectedSection.value) {
        return;
    }

    await issue(selectedSection.value.slug, category.slug);
}

async function issueService(service: ServiceSummary): Promise<void> {
    if (!selectedSection.value) {
        return;
    }

    await issue(selectedSection.value.slug, undefined, service.slug);
}

async function issue(sectionSlug: string, categorySlug?: string, serviceSlug?: string): Promise<void> {
    if (busy.value) {
        return;
    }

    if (!kioskToken.value) {
        error.value = 'Este quiosco no tiene un token válido.';
        return;
    }

    busy.value = true;
    error.value = '';
    ticket.value = null;
    printState.value = 'idle';

    try {
        const response = await api.issue(
            kioskToken.value,
            sectionSlug,
            categorySlug,
            serviceSlug,
        );
        ticket.value = response.data;

        if (!kioskPrinter.value) {
            printState.value = 'failed';
        } else {
            printState.value = 'printing';

            try {
                await createPrintDriver(
                    kioskPrinter.value.driver,
                    kioskPrinter.value.target,
                ).print(response.print.escpos_base64);
                printState.value = 'printed';
            } catch {
                printState.value = 'failed';
            }
        }

        if (resetTimer) {
            clearTimeout(resetTimer);
        }

        resetTimer = setTimeout(reset, 15000);
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'No se pudo emitir el ticket.';
    } finally {
        busy.value = false;
    }
}

function reset(): void {
    ticket.value = null;
    printState.value = 'idle';
    error.value = '';
}
</script>

<template>
    <main class="flex min-h-screen flex-col bg-brand-950 text-white">
        <header class="flex items-center justify-between border-b border-brand-800 px-8 py-5">
            <div class="flex items-center gap-4">
                <span class="rounded-2xl bg-white p-1.5">
                    <img :src="logoUrl" alt="CESSA" class="h-12 w-auto object-contain">
                </span>
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-accent-300">CESSA · Gestión de tickets</p>
                    <h1 class="text-3xl font-semibold">Quiosco de atención</h1>
                </div>
            </div>
            <button
                v-if="selectedSection"
                type="button"
                class="rounded-xl border border-brand-700 px-5 py-3 text-lg text-slate-200 transition hover:border-accent-300 hover:text-white"
                @click="clearSection"
            >
                Cambiar sección
            </button>
        </header>

        <section v-if="!selectedSection" class="flex flex-1 flex-col justify-center px-8 py-12">
            <h2 class="mb-10 text-center text-2xl text-slate-300">Seleccione el área de atención</h2>

            <div class="mx-auto grid w-full max-w-5xl gap-6 md:grid-cols-3">
                <button
                    v-for="section in sections"
                    :key="section.slug"
                    type="button"
                    class="rounded-3xl border border-brand-800 bg-brand-900 p-10 text-left transition hover:-translate-y-1 hover:border-accent-300"
                    @click="selectSection(section)"
                >
                    <span class="text-sm uppercase tracking-[0.2em] text-accent-300">Sección</span>
                    <span class="mt-4 block text-2xl font-semibold">{{ section.name }}</span>
                    <span class="mt-6 block text-lg text-slate-400">
                        {{ section.services.length > 0
                            ? `${section.services.length} servicios disponibles`
                            : `${section.codes.length} tipos de atención` }}
                    </span>
                </button>
            </div>

            <p v-if="error" class="mt-8 text-center text-lg text-red-400">{{ error }}</p>
        </section>

        <section v-else class="flex flex-1 flex-col px-8 py-10">
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-accent-300">Sección seleccionada</p>
                    <h2 class="text-3xl font-semibold">{{ selectedSection.name }}</h2>
                </div>
                <p class="text-lg text-slate-400">Elija el motivo de su visita</p>
            </div>

            <div v-if="!ticket" class="mx-auto w-full max-w-5xl">
                <div v-if="services.length > 0" class="mb-10">
                    <h3 class="text-sm uppercase tracking-[0.25em] text-emerald-400">Motivos de atención</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <button
                            v-for="service in services"
                            :key="service.slug"
                            type="button"
                            class="rounded-3xl border border-emerald-500/30 bg-emerald-500/5 p-8 text-left transition hover:-translate-y-1 hover:border-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="busy"
                            @click="issueService(service)"
                        >
                            <span class="text-sm uppercase tracking-[0.2em] text-emerald-400">
                                {{ service.category?.is_preferential ? 'Atención preferencial' : 'Atención general' }}
                            </span>
                            <span class="mt-3 block text-2xl font-semibold">{{ service.name }}</span>
                            <span v-if="service.description" class="mt-2 block text-sm text-slate-400">
                                {{ service.description }}
                            </span>
                        </button>
                    </div>
                </div>

                <template v-else>
                    <h3 class="text-sm uppercase tracking-[0.25em] text-accent-300">Atención por categoría</h3>
                    <div class="mt-4 grid gap-6 md:grid-cols-2">
                        <button
                            v-for="category in categories"
                            :key="category.slug"
                            type="button"
                            class="rounded-3xl border border-brand-800 bg-brand-900 p-12 text-left transition hover:-translate-y-1 hover:border-accent-300 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="busy"
                            @click="issueCategory(category)"
                        >
                            <span class="text-sm uppercase tracking-[0.2em] text-accent-300">
                                {{ category.is_preferential ? 'Atención preferencial' : 'Atención general' }}
                            </span>
                            <span class="mt-4 block text-3xl font-semibold">{{ category.name }}</span>
                        </button>
                    </div>
                </template>
            </div>

            <div v-else class="mx-auto w-full max-w-3xl rounded-[2rem] border border-accent-300/50 bg-brand-900 p-14 text-center">
                <p class="text-lg uppercase tracking-[0.3em] text-accent-300">Su ticket</p>
                <p class="mt-6 text-8xl font-black tracking-tight">{{ ticket.label }}</p>
                <p class="mt-6 text-2xl text-slate-300">
                    {{ ticket.service?.name ?? ticket.category?.name }}
                </p>
                <p class="mt-3 text-lg text-slate-500">{{ ticket.section?.name }}</p>

                <p v-if="printState === 'printing'" class="mt-10 text-lg text-accent-300">
                    Imprimiendo su ticket…
                </p>
                <p v-else-if="printState === 'printed'" class="mt-10 text-lg text-emerald-400">
                    Ticket impreso. Espere su llamado.
                </p>
                <p v-else-if="printState === 'failed'" class="mt-10 text-lg text-amber-400">
                    No se pudo imprimir. Conserve este código en pantalla.
                </p>

                <button
                    type="button"
                    class="mt-10 rounded-2xl bg-accent-300 px-8 py-4 text-xl font-semibold text-brand-950 transition hover:bg-accent-200"
                    @click="reset"
                >
                    Emitir otro ticket
                </button>
            </div>

            <p v-if="error" class="mt-8 text-center text-lg text-red-400">{{ error }}</p>
        </section>
    </main>
</template>
