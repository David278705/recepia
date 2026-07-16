<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, default: 'sand' },
});

const toneClasses = {
    brand: 'bg-brand-100 text-brand-800',
    amber: 'bg-amber-100 text-amber-800',
    sand: 'bg-sand-100 text-sand-700',
    urgent: 'bg-pilo-escalation text-white',
};

const classes = computed(() => toneClasses[props.tone] ?? toneClasses.sand);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="classes"
    >
        <slot />
    </span>
</template>
