<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-sand-300 px-6 py-14 text-center">
        <component :is="icon" v-if="icon" class="mb-3 h-10 w-10 text-sand-300" />
        <h3 class="font-display text-base font-semibold text-sand-700">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-sand-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4"><slot name="action" /></div>
    </div>
</template>
