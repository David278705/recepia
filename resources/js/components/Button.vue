<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});

const variantClasses = {
    primary: 'bg-brand-800 text-white shadow-sm hover:bg-brand-900 focus-visible:outline-brand-800',
    accent: 'bg-amber-500 text-brand-950 shadow-sm hover:bg-amber-400 focus-visible:outline-amber-500',
    ghost: 'bg-white text-sand-700 hover:bg-sand-50 border border-sand-300',
    danger: 'bg-transparent text-amber-800 hover:bg-amber-50 border border-amber-200',
};

const classes = computed(() => variantClasses[props.variant] ?? variantClasses.primary);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition
            focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
            disabled:cursor-not-allowed disabled:opacity-50"
        :class="classes"
    >
        <slot />
    </button>
</template>
