<script setup>
defineProps({
    size: { type: [Number, String], default: 32 },
    // Se conserva por compatibilidad con los usos existentes: el logo (la
    // mascota en círculo) funciona igual sobre fondos claros y oscuros.
    variant: { type: String, default: 'default' },
});
</script>

<template>
    <img
        :src="'/img/logo-round.png'"
        alt=""
        :width="size"
        :height="size"
        class="rounded-full select-none"
        :style="{ width: size + 'px', height: size + 'px' }"
        draggable="false"
    >
</template>
