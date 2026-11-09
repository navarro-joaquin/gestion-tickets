<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '../../../shared/api/client';
import type { KioskDevice, Printer } from '../../../shared/types/api';

interface PrinterForm {
    id: number | null;
    kiosk_device_id: number | null;
    name: string;
    driver: 'local' | 'qz';
    target: string;
    paper_width: number;
    is_active: boolean;
}

const printers = ref<Printer[]>([]);
const kiosks = ref<KioskDevice[]>([]);
const form = ref<PrinterForm>(emptyForm());
const message = ref('');
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    await loadKiosks();
    await load();
});

async function loadKiosks(): Promise<void> {
    try {
        const response = await api.adminKiosks();
        kiosks.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

async function load(): Promise<void> {
    try {
        const response = await api.adminPrinters();
        printers.value = response.data;
    } catch (exception) {
        error.value = describeError(exception);
    }
}

function edit(printer: Printer): void {
    form.value = {
        id: printer.id,
        kiosk_device_id: printer.kiosk_device?.id ?? null,
        name: printer.name,
        driver: printer.driver,
        target: printer.target ?? '',
        paper_width: printer.paper_width,
        is_active: printer.is_active,
    };
}

function reset(): void {
    form.value = emptyForm();
}

async function save(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    const payload = {
        kiosk_device_id: form.value.kiosk_device_id,
        name: form.value.name,
        driver: form.value.driver,
        target: form.value.target.trim() === '' ? null : form.value.target.trim(),
        paper_width: form.value.paper_width,
        is_active: form.value.is_active,
    };

    try {
        const response = form.value.id
            ? await api.updatePrinter(form.value.id, payload)
            : await api.createPrinter(payload);

        message.value = response.message;
        reset();
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

async function remove(printer: Printer): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await api.deletePrinter(printer.id);
        message.value = response.message;
        await load();
    } catch (exception) {
        error.value = describeError(exception);
    } finally {
        busy.value = false;
    }
}

function emptyForm(): PrinterForm {
    return {
        id: null,
        kiosk_device_id: null,
        name: '',
        driver: 'local',
        target: '',
        paper_width: 80,
        is_active: true,
    };
}

function describeError(exception: unknown): string {
    return exception instanceof Error ? exception.message : 'Ocurrió un error inesperado.';
}
</script>

<template>
    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <form class="rounded-3xl border border-slate-800 bg-slate-900 p-8" @submit.prevent="save">
            <h2 class="text-xl font-semibold">{{ form.id ? 'Editar impresora' : 'Nueva impresora' }}</h2>
            <p class="mt-2 text-sm text-slate-400">
                Driver <code class="text-sky-300">local</code>: URL del servicio HTTP. Driver
                <code class="text-sky-300">qz</code>: nombre de la impresora en QZ Tray.
            </p>

            <div class="mt-6 grid gap-4">
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Quiosco
                    <select v-model="form.kiosk_device_id" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                        <option :value="null">Sin quiosco</option>
                        <option v-for="kiosk in kiosks" :key="kiosk.id" :value="kiosk.id">{{ kiosk.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Nombre
                    <input v-model="form.name" required maxlength="80" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-2 text-sm text-slate-300">
                        Driver
                        <select v-model="form.driver" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                            <option value="local">Servicio local</option>
                            <option value="qz">QZ Tray</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-2 text-sm text-slate-300">
                        Ancho de papel
                        <select v-model.number="form.paper_width" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                            <option :value="58">58 mm</option>
                            <option :value="80">80 mm</option>
                        </select>
                    </label>
                </div>
                <label class="flex flex-col gap-2 text-sm text-slate-300">
                    Destino
                    <input v-model="form.target" maxlength="255" :placeholder="form.driver === 'qz' ? 'EPSON TM-T20II' : 'http://127.0.0.1:9100'" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3">
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950">
                    Impresora activa
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy">
                    {{ form.id ? 'Guardar cambios' : 'Crear impresora' }}
                </button>
                <button v-if="form.id" type="button" class="rounded-xl border border-slate-700 px-5 py-3 font-semibold text-slate-300" @click="reset">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="space-y-3">
            <p v-if="message" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-emerald-200">{{ message }}</p>
            <p v-if="error" class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-red-200">{{ error }}</p>

            <article v-for="printer in printers" :key="printer.id" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ printer.name }}</h3>
                        <p class="text-sm text-slate-400">
                            {{ printer.kiosk_device ? printer.kiosk_device.name : 'Sin quiosco' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ printer.driver }} · {{ printer.paper_width }} mm · {{ printer.target || 'sin destino' }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="printer.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400'">
                        {{ printer.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-sky-500" @click="edit(printer)">Editar</button>
                    <button type="button" class="rounded-lg border border-red-500/40 px-3 py-2 text-sm text-red-200 hover:border-red-400" @click="remove(printer)">Eliminar</button>
                </div>
            </article>
        </div>
    </section>
</template>
