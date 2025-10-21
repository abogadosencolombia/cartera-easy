<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  modelValue: [Object, null],
  endpoint: { type: String, required: true },
  minChars: { type: Number, default: 2 },
  placeholder: { type: String, default: 'Buscar…' },
});

const emit = defineEmits(['update:modelValue']);

function debounce(fn, delay) {
  let timeoutId = null;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
}

const root = ref(null);
const query = ref('');
const open = ref(false);
const loading = ref(false);
const items = ref([]);

const fetchData = debounce(async () => {
  const q = query.value.trim();
  if (q.length < props.minChars) {
    items.value = [];
    return;
  }
  loading.value = true;
  try {
    const response = await axios.get(props.endpoint, { params: { q } });
    items.value = response.data;
  } catch (error) {
    console.error('Error fetching async options:', error);
    items.value = [];
  } finally {
    loading.value = false;
  }
}, 300);

watch(query, (newQuery) => {
  if (newQuery !== (props.modelValue?.label ?? '')) {
    fetchData();
  }
});

// Mantiene el input sincronizado si el padre cambia el v-model externamente
watch(
  () => props.modelValue,
  (v) => { query.value = v?.label ?? ''; },
  { immediate: true }
);

function selectItem(item) {
  query.value = item.label;
  emit('update:modelValue', item);
  open.value = false;
}

function onFocus() {
  open.value = true;
  if (items.value.length === 0 && query.value.length >= props.minChars) {
    fetchData();
  }
}

function handleClickOutside(e) {
  if (!root.value?.contains(e.target)) {
    open.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div ref="root" class="relative">
    <input
      type="text"
      v-model="query"
      :placeholder="placeholder"
      @focus="onFocus"
      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
      autocomplete="off"
    />

    <div
      v-if="open"
      class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-auto"
    >
      <div v-if="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
        Buscando…
      </div>
      <div v-else-if="items.length > 0">
        <button
          v-for="opt in items"
          :key="opt.id"
          type="button"
          @mousedown.prevent="selectItem(opt)"
          class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200"
        >
          {{ opt.label }}
        </button>
      </div>
      <div v-else-if="query.length >= minChars" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
        Sin resultados para "{{ query }}"
      </div>
      <div v-else class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
        Escribe para buscar...
      </div>
    </div>
  </div>
</template>
