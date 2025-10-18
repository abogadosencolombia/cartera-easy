<script setup>
const props = defineProps({
  links: { type: Array, default: () => [] },
});
</script>

<template>
  <div v-if="links && links.length" class="relative">
    <details class="group">
      <summary class="list-none inline-flex items-center gap-2 px-3 py-1 text-sm border rounded cursor-pointer hover:bg-gray-50">
        Redes
        <svg class="w-4 h-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.08 1.04l-4.25 4.25a.75.75 0 01-1.08 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
        </svg>
      </summary>
      <div class="absolute z-20 mt-2 w-56 rounded border bg-white shadow">
        <ul class="py-1 text-sm">
          <li v-for="(l, i) in links" :key="i">
            <a :href="l.url" target="_blank" rel="noopener"
               class="block px-3 py-2 hover:bg-gray-50 truncate">
              <span class="text-gray-500 mr-1">•</span>
              {{ l.label || l.url }}
            </a>
          </li>
        </ul>
      </div>
    </details>
  </div>
</template>
