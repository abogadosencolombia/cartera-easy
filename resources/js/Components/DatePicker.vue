<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, XMarkIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    placeholder: { type: String, default: 'Seleccionar fecha' }
});

const emit = defineEmits(['update:modelValue']);

// --- LÓGICA DE POSICIONAMIENTO (Anti-recorte en modales) ---
const triggerRef = ref(null);
const panelStyle = ref({ position: 'fixed', zIndex: 9999 });

const updatePanelPosition = () => {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    panelStyle.value = {
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
        width: '18rem',
        position: 'fixed',
        zIndex: 99999
    };
};

// --- LÓGICA DE FECHA SEGURA ---
const parseDate = (dateStr) => {
    if (!dateStr || dateStr === '' || dateStr === '0000-00-00') return null;
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);
    const date = new Date(year, month, day);
    return isNaN(date.getTime()) ? null : date;
};

const formatDate = (date) => {
    if (!date || isNaN(date.getTime())) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const selectedDate = computed(() => parseDate(props.modelValue));
const viewDate = ref(selectedDate.value || new Date());

// Sincronizar vista si el valor externo cambia
watch(() => props.modelValue, (newVal) => {
    const parsed = parseDate(newVal);
    if (parsed) viewDate.ref = parsed;
});

const currentMonth = computed(() => viewDate.value.getMonth());
const currentYear = computed(() => viewDate.value.getFullYear());

const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const daysOfWeek = ['Do','Lu','Ma','Mi','Ju','Vi','Sa'];

const days = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    const prevLastDate = new Date(year, month, 0).getDate();
    
    const res = [];
    for (let i = firstDay - 1; i >= 0; i--) res.push({ date: new Date(year, month - 1, prevLastDate - i), current: false });
    for (let i = 1; i <= lastDate; i++) res.push({ date: new Date(year, month, i), current: true });
    const remain = 42 - res.length;
    for (let i = 1; i <= remain; i++) res.push({ date: new Date(year, month + 1, i), current: false });
    return res;
});

const selectDate = (date, close) => {
    emit('update:modelValue', formatDate(date));
    close();
};

const displayValue = computed(() => {
    const d = selectedDate.value;
    if (!d || isNaN(d.getTime())) return '';
    return d.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
});

// --- FESTIVOS ---
const getHolidayName = (date) => {
    const d = date.getDate();
    const m = date.getMonth();
    // Simplificado para brevedad, pero manteniendo lógica básica
    if (d === 1 && m === 0) return 'Año Nuevo';
    if (d === 1 && m === 4) return 'Día del Trabajo';
    if (d === 20 && m === 6) return 'Independencia';
    if (d === 7 && m === 7) return 'Batalla de Boyacá';
    if (d === 8 && m === 11) return 'Inmaculada Concepción';
    if (d === 25 && m === 11) return 'Navidad';
    return null;
};
</script>

<template>
    <Popover v-slot="{ open }" class="relative w-full">
        <div ref="triggerRef" class="relative group">
            <PopoverButton as="div" class="w-full" @click="updatePanelPosition">
                <button type="button" class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer">
                    <div class="flex items-center gap-2 truncate">
                        <CalendarDaysIcon class="h-5 w-5 text-gray-400 group-hover:text-indigo-500" />
                        <span :class="modelValue ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-400'">
                            {{ displayValue || placeholder }}
                        </span>
                    </div>
                </button>
            </PopoverButton>
            <button v-if="modelValue" @click.stop="emit('update:modelValue', '')" type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 z-10"><XMarkIcon class="h-4 w-4" /></button>
        </div>

        <teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-1 opacity-0">
                <PopoverPanel v-slot="{ close }" class="fixed shadow-2xl z-[99999]" :style="panelStyle">
                    <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-700 shadow-2xl">
                        <div class="px-2 py-3 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                            <button @click="viewDate = new Date(currentYear, currentMonth - 1, 1)" type="button" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-gray-600 dark:text-gray-400"><ChevronLeftIcon class="h-4 w-4" /></button>
                            <span class="text-xs font-black uppercase text-gray-900 dark:text-white">{{ monthNames[currentMonth] }} {{ currentYear }}</span>
                            <button @click="viewDate = new Date(currentYear, currentMonth + 1, 1)" type="button" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-gray-600 dark:text-gray-400"><ChevronRightIcon class="h-4 w-4" /></button>
                        </div>
                        <div class="p-3">
                            <div class="grid grid-cols-7 mb-2">
                                <div v-for="day in daysOfWeek" :key="day" class="text-center text-[10px] font-black text-gray-400 uppercase">{{ day }}</div>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <button v-for="(item, idx) in days" :key="idx" @click="selectDate(item.date, close)" type="button" class="h-8 w-8 flex items-center justify-center rounded-lg text-xs transition-all" :class="[item.current ? 'text-gray-700 dark:text-gray-200' : 'text-gray-300 dark:text-gray-600 opacity-50', (modelValue && item.date.toDateString() === selectedDate?.toDateString()) ? 'bg-indigo-600 text-white font-bold' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30']">{{ item.date.getDate() }}</button>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t dark:border-gray-700 flex justify-between bg-gray-50 dark:bg-gray-800/50">
                            <button @click="viewDate = new Date();" type="button" class="text-[10px] font-bold text-indigo-600 uppercase">Hoy</button>
                            <button @click="emit('update:modelValue', ''); close();" type="button" class="text-[10px] font-bold text-gray-500 uppercase">Limpiar</button>
                        </div>
                    </div>
                </PopoverPanel>
            </transition>
        </teleport>
    </Popover>
</template>
