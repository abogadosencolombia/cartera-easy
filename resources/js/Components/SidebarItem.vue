<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  item: { type: Object, required: true },
});

const isRouteActive = (patterns) => {
  if (!patterns) return false;
  const currentRoute = route().current();
  if (!Array.isArray(patterns)) patterns = [patterns];
  // Usamos startsWith para que rutas como 'casos.show' activen 'casos.*'
  return patterns.some((p) => currentRoute.startsWith(p.replace(/\.\*/g, '')));
};

const isOpen = ref(isRouteActive(props.item.active));
const isDropdownActive = computed(() => isRouteActive(props.item.active));
const unreadCount = computed(() => usePage().props.auth?.unreadNotifications ?? 0);
</script>

<template>
  <div>
    <Link v-if="item.type === 'link' || item.type === 'notification'" :href="item.href"
          :class="['group flex items-center w-full px-3 py-2.5 text-sm font-medium rounded-md transition-colors',
                   isRouteActive(item.active) ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white']">
      <component :is="item.icon" :class="['h-6 w-6 mr-3', isRouteActive(item.active) ? 'text-white' : 'text-gray-400 group-hover:text-gray-300']" aria-hidden="true" />
      <span class="truncate flex-1">{{ item.label }}</span>
      <span v-if="item.type === 'notification' && unreadCount > 0"
            class="ml-auto inline-block py-0.5 px-2 text-xs rounded-full bg-red-500 text-white">
        {{ unreadCount }}
      </span>
    </Link>

    <div v-else-if="item.type === 'dropdown'">
      <button @click="isOpen = !isOpen"
              :class="['group flex items-center w-full px-3 py-2.5 text-sm font-medium rounded-md transition-colors',
                       isDropdownActive ? 'text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white']">
        <component :is="item.icon" :class="['h-6 w-6 mr-3', isDropdownActive ? 'text-white' : 'text-gray-400 group-hover:text-gray-300']" aria-hidden="true" />
        <span class="flex-1 text-left truncate">{{ item.label }}</span>
        <svg :class="['h-5 w-5 transform transition-transform duration-200', isOpen ? 'rotate-90' : '']" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>
      <div v-show="isOpen" class="mt-1 space-y-1 pl-5" role="region">
        <template v-for="(subItem, index) in item.items" :key="index">
          <div v-if="subItem.type === 'divider'" class="border-t border-gray-700 my-2 mx-4"></div>
          <a v-else-if="subItem.type === 'external'" :href="subItem.href" target="_blank" rel="noopener noreferrer"
             class="group flex items-center w-full pl-4 pr-3 py-2 text-sm font-medium rounded-md text-gray-400 hover:bg-gray-700 hover:text-white">
            {{ subItem.label }}
             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-auto text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
          </a>
          <Link v-else :href="subItem.href"
                :class="['group flex items-center w-full pl-4 pr-3 py-2 text-sm font-medium rounded-md', isRouteActive(subItem.active) ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white']">
            <component v-if="subItem.icon" :is="subItem.icon" class="h-4 w-4 mr-3 text-gray-500" aria-hidden="true" />
            {{ subItem.label }}
          </Link>
        </template>
      </div>
    </div>
  </div>
</template>