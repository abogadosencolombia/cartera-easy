<script setup>
import { ref, computed, watch } from 'vue';
import { useElementBounding, useWindowSize } from '@vueuse/core';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, XMarkIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';
import { isHoliday } from '@/Utils/holidays';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    placeholder: { type: String, default: 'Seleccionar fecha' }
});

const emit = defineEmits(['update:modelValue']);
const container = ref(null);

const { bottom, left, top } = useElementBounding(container);
const { height: windowHeight } = useWindowSize();

const floatingStyles = computed(() => {
    const dropdownHeight = 380; // Aproximado para el calendario
    const spaceBelow = windowHeight.value - bottom.value;
    const showAbove = spaceBelow < dropdownHeight && top.value > dropdownHeight;

    return {
        top: showAbove ? `${top.value - dropdownHeight - 8}px` : `${bottom.value}px`,
        left: `${left.value}px`,
    };
});

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

watch(() => props.modelValue, (newVal) => {
    const parsed = parseDate(newVal);
    if (parsed) viewDate.value = parsed;
});

const currentMonth = computed(() => viewDate.value.getMonth());
const currentYear = computed(() => viewDate.value.getFullYear());

const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const daysOfWeek = ['Do','Lu','Ma','Mi','Ju','Vi','Sa'];

const years = computed(() => {
    const cy = new Date().getFullYear();
    const arr = [];
    for (let i = cy - 20; i <= cy + 10; i++) arr.push(i);
    if (!arr.includes(currentYear.value)) arr.push(currentYear.value);
    return arr.sort((a, b) => a - b);
});

const days = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    const prevLastDate = new Date(year, month, 0).getDate();
    
    const res = [];
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = new Date(year, month - 1, prevLastDate - i);
        res.push({ date: d, current: false, holiday: isHoliday(d) });
    }
    for (let i = 1; i <= lastDate; i++) {
        const d = new Date(year, month, i);
        res.push({ date: d, current: true, holiday: isHoliday(d) });
    }
    const remain = 42 - res.length;
    for (let i = 1; i <= remain; i++) {
        const d = new Date(year, month + 1, i);
        res.push({ date: d, current: false, holiday: isHoliday(d) });
    }
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

const updateViewDate = (part, value) => {
    const newDate = new Date(viewDate.value);
    if (part === 'month') newDate.setMonth(value);
    if (part === 'year') newDate.setFullYear(value);
    viewDate.value = newDate;
};
</script>

<template>
    <Popover v-slot="{ open }" class="relative w-full" ref="container">
        <div class="relative group">
            <PopoverButton as="div" class="w-full">
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

        <Teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-1 opacity-0">
                <PopoverPanel v-slot="{ close }" class="fixed z-[9999] mt-2 w-72 origin-top-left" :style="floatingStyles">
                    <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-700 shadow-2xl ring-1 ring-black ring-opacity-5">
                        <!-- Header con selectores de mes y año mejorados -->
                        <div class="px-3 py-3 border-b dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/80 backdrop-blur-sm flex items-center gap-2">
                            <button @click="updateViewDate('month', currentMonth - 1)" type="button" class="p-1.5 hover:bg-white dark:hover:bg-gray-700 rounded-full text-gray-500 dark:text-gray-400 border border-transparent hover:border-gray-200 dark:hover:border-gray-600 transition-all shadow-sm">
                                <ChevronLeftIcon class="h-4 w-4" />
                            </button>
                            
                            <div class="flex flex-1 items-center gap-1.5">
                                <div class="relative flex-1 group">
                                    <select :value="currentMonth" @change="updateViewDate('month', parseInt($event.target.value))" class="appearance-none w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg py-1 px-2 pr-6 text-[11px] font-extrabold uppercase tracking-tight text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-all shadow-sm custom-select-compact">
                                        <option v-for="(name, i) in monthNames" :key="name" :value="i" class="bg-white dark:bg-gray-800">{{ name }}</option>
                                    </select>
                                    <ChevronDownIcon class="absolute right-1.5 top-1/2 -translate-y-1/2 h-3 w-3 text-gray-400 pointer-events-none group-hover:text-indigo-500 transition-colors" />
                                </div>

                                <div class="relative group">
                                    <select :value="currentYear" @change="updateViewDate('year', parseInt($event.target.value))" class="appearance-none bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg py-1 px-2 pr-6 text-[11px] font-extrabold text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-all shadow-sm custom-select-compact">
                                        <option v-for="y in years" :key="y" :value="y" class="bg-white dark:bg-gray-800">{{ y }}</option>
                                    </select>
                                    <ChevronDownIcon class="absolute right-1.5 top-1/2 -translate-y-1/2 h-3 w-3 text-gray-400 pointer-events-none group-hover:text-indigo-500 transition-colors" />
                                </div>
                            </div>

                            <button @click="updateViewDate('month', currentMonth + 1)" type="button" class="p-1.5 hover:bg-white dark:hover:bg-gray-700 rounded-full text-gray-500 dark:text-gray-400 border border-transparent hover:border-gray-200 dark:hover:border-gray-600 transition-all shadow-sm">
                                <ChevronRightIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="p-3">
                            <div class="grid grid-cols-7 mb-2">
                                <div v-for="day in daysOfWeek" :key="day" class="text-center text-[10px] font-black text-gray-400 uppercase">{{ day }}</div>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <button v-for="(item, idx) in days" :key="idx" @click="selectDate(item.date, close)" type="button" class="h-8 w-8 flex flex-col items-center justify-center rounded-lg text-xs transition-all relative" :title="item.holiday || ''" :class="[
                                    item.current ? 'text-gray-700 dark:text-gray-200' : 'text-gray-300 dark:text-gray-600 opacity-50', 
                                    (modelValue && item.date.toDateString() === selectedDate?.toDateString()) ? 'bg-indigo-600 text-white font-bold' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30',
                                    item.holiday ? 'text-red-600 dark:text-red-400 font-bold' : ''
                                ]">
                                    {{ item.date.getDate() }}
                                    <span v-if="item.holiday" class="absolute bottom-0.5 w-1 h-1 bg-red-500 rounded-full"></span>
                                </button>
                            </div>
                        </div>
                        <div class="px-4 py-2 border-t dark:border-gray-700 flex justify-between bg-gray-50 dark:bg-gray-800/50">
                            <button @click="viewDate = new Date();" type="button" class="text-[10px] font-bold text-indigo-600 uppercase hover:underline transition-all">Hoy</button>
                            <button @click="emit('update:modelValue', ''); close();" type="button" class="text-[10px] font-bold text-gray-500 uppercase hover:underline transition-all">Limpiar</button>
                        </div>
                    </div>
                </PopoverPanel>
            </transition>
        </Teleport>
    </Popover>
</template>

<style scoped>
.custom-select-compact {
    max-height: 150px;
}
/* Estilo para que el menú del select nativo no sea tan largo en navegadores que lo soportan */
select option {
    padding: 8px;
    font-size: 14px;
}
</style>
