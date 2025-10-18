<script setup>
const props = defineProps({
  title:  { type: String, required: true },
  metric: { type: [String, Number], required: true },
  icon:   { type: [Object, Function], default: null }, // <- acepta ambas
  help:   { type: String, default: '' },
  trend:  { type: Object, default: null }, // { up: true/false, label: '...' }
});
</script>

<template>
  <div class="rounded-lg bg-white shadow p-4 flex items-start gap-3">
    <div v-if="icon" class="p-2 rounded-md bg-gray-50">
      <component v-if="icon" :is="icon" class="w-5 h-5" />
    </div>

    <div class="flex-1">
      <div class="text-sm text-gray-500">{{ title }}</div>
      <div class="text-2xl font-semibold text-gray-900">{{ metric }}</div>

      <div v-if="trend" class="text-xs mt-1" :class="trend.up ? 'text-green-600' : 'text-red-600'">
        {{ trend.label }}
      </div>

      <div v-if="help" class="text-xs text-gray-400 mt-1">
        {{ help }}
      </div>
    </div>
  </div>
</template>
