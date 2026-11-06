<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { MediaItem } from '../../../shared/types/api';

const props = defineProps<{
    items: MediaItem[];
}>();

const index = ref(0);
let timer: ReturnType<typeof setTimeout> | undefined;

const current = computed<MediaItem | null>(() => {
    if (props.items.length === 0) {
        return null;
    }

    return props.items[index.value % props.items.length] ?? null;
});

const signature = computed<string>(() =>
    props.items
        .map((item) => `${item.playlist}:${item.media.id}:${item.duration_seconds ?? ''}`)
        .join('|'),
);

watch(
    signature,
    () => {
        index.value = 0;
        schedule();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearTimer();
});

function clearTimer(): void {
    if (timer) {
        clearTimeout(timer);
        timer = undefined;
    }
}

function next(): void {
    if (props.items.length === 0) {
        return;
    }

    index.value = (index.value + 1) % props.items.length;
    schedule();
}

function schedule(): void {
    clearTimer();

    const item = current.value;

    if (!item || item.media === null || item.media.type === 'video') {
        return;
    }

    const seconds = item.duration_seconds ?? item.media.duration_seconds ?? 10;

    timer = setTimeout(next, Math.max(3, seconds) * 1000);
}
</script>

<template>
    <div class="relative flex h-full min-h-[24rem] flex-col overflow-hidden rounded-[2rem] border border-slate-800 bg-black">
        <div v-if="current && current.media" class="relative flex flex-1 items-center justify-center">
            <video
                v-if="current.media.type === 'video'"
                :key="`${current.media.id}-${index}`"
                :src="current.media.url"
                class="h-full w-full object-contain"
                autoplay
                muted
                playsinline
                @ended="next"
            />
            <img
                v-else
                :key="`${current.media.id}-${index}`"
                :src="current.media.url"
                :alt="current.media.name"
                class="h-full w-full object-contain"
            >

            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-6 pb-4 pt-12">
                <p class="text-sm uppercase tracking-[0.3em] text-sky-300">{{ current.playlist }}</p>
                <p class="text-xl font-semibold text-white">{{ current.media.name }}</p>
            </div>
        </div>

        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center text-slate-500"
        >
            <p class="text-2xl font-semibold">Sin contenido multimedia</p>
            <p class="text-sm">Asigne una playlist con contenidos activos a esta pantalla.</p>
        </div>

        <div v-if="props.items.length > 1" class="flex items-center justify-center gap-2 bg-slate-950/80 py-3">
            <button
                v-for="(item, position) in props.items"
                :key="`${item.media.id}-${position}`"
                type="button"
                class="h-2 rounded-full transition-all"
                :class="position === index % props.items.length ? 'w-8 bg-sky-400' : 'w-2 bg-slate-700'"
                :aria-label="`Ver ${item.media.name}`"
                @click="index = position; schedule()"
            />
        </div>
    </div>
</template>
