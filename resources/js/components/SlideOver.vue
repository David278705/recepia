<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
});
const emit = defineEmits(['close']);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) emit('close');
}

watch(
    () => props.open,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

window.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="slideover-backdrop">
            <div v-if="open" class="fixed inset-0 z-40 bg-sand-950/40" @click="emit('close')" />
        </Transition>
        <Transition name="slideover-panel">
            <aside v-if="open" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-lg flex-col bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-sand-200 px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-brand-900">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-sand-500 transition hover:bg-sand-100"
                        @click="emit('close')"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <slot />
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
