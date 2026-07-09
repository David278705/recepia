<script setup>
defineProps({
    size: { type: [Number, String], default: 32 },
    // 'default' para fondos claros (tesela verde), 'inverse' para fondos oscuros
    variant: { type: String, default: 'default' },
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <template v-if="variant === 'inverse'">
            <rect width="32" height="32" rx="8" fill="#faf8f5" />
            <rect x="7" y="9" width="18" height="13" rx="4.5" fill="#063d37" />
            <path d="M10 21h5l-5 5v-5z" fill="#063d37" />
            <circle cx="12.5" cy="15.5" r="1.6" fill="#faf8f5" />
            <circle cx="16" cy="15.5" r="1.6" fill="#f59e0b" />
            <circle cx="19.5" cy="15.5" r="1.6" fill="#faf8f5" />
        </template>
        <template v-else>
            <rect width="32" height="32" rx="8" fill="#063d37" />
            <rect x="7" y="9" width="18" height="13" rx="4.5" fill="#faf8f5" />
            <path d="M10 21h5l-5 5v-5z" fill="#faf8f5" />
            <circle cx="12.5" cy="15.5" r="1.6" fill="#063d37" />
            <circle cx="16" cy="15.5" r="1.6" fill="#f59e0b" />
            <circle cx="19.5" cy="15.5" r="1.6" fill="#063d37" />
        </template>
    </svg>
</template>
