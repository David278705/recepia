<script setup>
import { computed, ref } from 'vue';

// Gráfico de barras de una sola serie (identidad la da el título de la
// tarjeta que lo contiene, por eso no lleva leyenda). Hover por barra con
// tooltip; el valor máximo lleva etiqueta directa fija.
const props = defineProps({
    // [{ label: 'lun', value: 3, hint: 'Lunes 30 jun' }]
    items: { type: Array, required: true },
    unit: { type: String, default: '' },
});

const hovered = ref(null);

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));

const heightPct = (value) => Math.round((value / max.value) * 100);
</script>

<template>
    <div>
        <div class="flex h-36 items-end gap-2">
            <div
                v-for="(item, index) in items"
                :key="item.label + index"
                class="group relative flex h-full flex-1 cursor-default flex-col justify-end"
                @mouseenter="hovered = index"
                @mouseleave="hovered = null"
            >
                <div
                    class="pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-brand-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow transition-opacity"
                    :class="hovered === index ? 'opacity-100' : 'opacity-0'"
                >
                    <span class="font-semibold">{{ item.value }}</span> {{ unit }}
                    <span v-if="item.hint" class="text-brand-200"> · {{ item.hint }}</span>
                </div>

                <span
                    v-if="item.value === max && item.value > 0"
                    class="mb-1 text-center font-mono text-xs text-sand-500"
                >{{ item.value }}</span>

                <div
                    class="mx-auto w-full max-w-8 rounded-t transition-colors"
                    :class="item.value > 0 ? 'bg-brand-500 group-hover:bg-brand-600' : 'bg-sand-200'"
                    :style="{ height: item.value > 0 ? heightPct(item.value) + '%' : '3px' }"
                />
            </div>
        </div>

        <div class="mt-1.5 flex gap-2 border-t border-sand-100 pt-1.5">
            <span
                v-for="(item, index) in items"
                :key="'l' + item.label + index"
                class="flex-1 text-center text-xs text-sand-500"
            >{{ item.label }}</span>
        </div>
    </div>
</template>
