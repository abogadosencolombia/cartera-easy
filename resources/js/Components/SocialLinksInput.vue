<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const links = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
});

function addRow(preset = null) {
  const base = { label: '', url: '' };
  if (preset) Object.assign(base, preset);
  links.value = [...links.value, base];
}
function removeRow(i) {
  links.value = links.value.filter((_, idx) => idx !== i);
}

const presets = [
  { label: 'Facebook',  url: 'https://facebook.com/' },
  { label: 'Instagram', url: 'https://instagram.com/' },
  { label: 'Twitter/X', url: 'https://x.com/' },
  { label: 'LinkedIn',  url: 'https://www.linkedin.com/in/' },
  { label: 'TikTok',    url: 'https://www.tiktok.com/@' },
  { label: 'Web',       url: 'https://'},
];
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
      <span class="text-sm text-gray-600">Añadir rápido:</span>
      <button v-for="p in presets" :key="p.label" type="button"
              class="px-2 py-1 text-xs border rounded hover:bg-gray-50"
              @click="addRow(p)">
        {{ p.label }}
      </button>
      <button type="button" class="ml-auto px-3 py-1 text-xs border rounded hover:bg-gray-50"
              @click="addRow()">+ Enlace</button>
    </div>

    <div v-if="links.length === 0" class="text-sm text-gray-500">
      No hay enlaces. Usa “Añadir rápido” o “+ Enlace”.
    </div>

    <div v-for="(row, i) in links" :key="i" class="grid grid-cols-12 gap-2 items-center">
      <div class="col-span-3">
        <input v-model="row.label" type="text" placeholder="Etiqueta"
               class="w-full rounded border-gray-300 focus:ring-0 focus:border-gray-400" />
      </div>
      <div class="col-span-8">
        <input v-model="row.url" type="url" placeholder="https://…"
               class="w-full rounded border-gray-300 focus:ring-0 focus:border-gray-400" />
      </div>
      <div class="col-span-1 flex justify-end">
        <button type="button" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded"
                @click="removeRow(i)">Quitar</button>
      </div>
    </div>
  </div>
</template>
