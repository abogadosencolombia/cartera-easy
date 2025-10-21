<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  items: { type: Array, required: true },
  open: { type: Boolean, required: true },
});

const emit = defineEmits(['close']);

const query = ref('');
const searchInput = ref(null);
const activeIndex = ref(0);

const searchResults = computed(() => {
  const flatItems = [];
  props.items.forEach(item => {
    if (item.type === 'link' || item.type === 'notification') {
      flatItems.push({ ...item, group: 'Navegación' });
    } else if (item.type === 'dropdown') {
      item.items.forEach(subItem => {
        if (subItem.type !== 'divider') {
          flatItems.push({ ...subItem, group: item.label });
        }
      });
    }
  });

  if (query.value === '') return flatItems;

  return flatItems.filter(item =>
    item.label.toLowerCase().includes(query.value.toLowerCase())
  );
});

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    setTimeout(() => searchInput.value?.focus(), 100);
  }
});

watch(searchResults, () => {
  activeIndex.value = 0;
});

const closePalette = () => {
  emit('close');
  setTimeout(() => query.value = '', 200);
};

const goToResult = (index) => {
  const item = searchResults.value[index];
  if (!item) return;

  if (item.type === 'external') {
    window.open(item.href, '_blank');
  } else {
    router.visit(item.href);
  }
  closePalette();
};

const handleKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    activeIndex.value = (activeIndex.value + 1) % searchResults.value.length;
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    activeIndex.value = (activeIndex.value - 1 + searchResults.value.length) % searchResults.value.length;
  } else if (event.key === 'Enter') {
    event.preventDefault();
    goToResult(activeIndex.value);
  }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
  <TransitionRoot :show="open" as="template">
    <Dialog @close="closePalette" class="relative z-50">
      <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
      </TransitionChild>
      <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
        <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100" leave="ease-in duration-200" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95">
          <DialogPanel class="mx-auto max-w-xl transform divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-black ring-opacity-5 transition-all">
            <div class="relative">
              <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gray-400" />
              <input ref="searchInput" v-model="query" type="text" class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-0 sm:text-sm" placeholder="Buscar y navegar..." />
            </div>
            <div v-if="searchResults.length > 0" class="max-h-96 overflow-y-auto">
              <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="(item, index) in searchResults" :key="item.href"
                    @click="goToResult(index)"
                    :class="['p-4 cursor-pointer', { 'bg-indigo-600 text-white': activeIndex === index }]">
                  <div class="flex items-center">
                    <component v-if="item.icon" :is="item.icon" :class="['h-5 w-5 mr-3', activeIndex === index ? 'text-white' : 'text-gray-400']" />
                    <div class="flex flex-col">
                      <span :class="['text-sm font-medium', activeIndex === index ? 'text-white' : 'text-gray-900 dark:text-gray-200']">{{ item.label }}</span>
                      <span :class="['text-xs', activeIndex === index ? 'text-indigo-200' : 'text-gray-500']">{{ item.group }}</span>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
             <div v-else class="p-6 text-center">
              <p class="text-sm text-gray-500">No se encontraron resultados para "{{ query }}"</p>
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>