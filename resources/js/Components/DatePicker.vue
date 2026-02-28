<script setup>
import { ref, computed } from 'vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon, XMarkIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: {
        type: [String, null],
        default: null
    },
    placeholder: {
        type: String,
        default: 'Seleccionar fecha'
    }
});

const emit = defineEmits(['update:modelValue']);

// Nombres en español
const monthNames = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
const daysOfWeek = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'];

// Lógica de fecha
const parseDate = (dateStr) => {
    if (!dateStr) return null;
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (date) => {
    if (!date) return '';
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const selectedDate = computed(() => parseDate(props.modelValue));
const viewDate = ref(selectedDate.value || new Date());

const currentMonth = computed(() => viewDate.value.getMonth());
const currentYear = computed(() => viewDate.value.getFullYear());

const days = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    
    const firstDayOfMonth = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    const dateArray = [];
    
    // Días mes anterior
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = firstDayOfMonth - 1; i >= 0; i--) {
        dateArray.push({
            date: new Date(year, month - 1, prevMonthLastDay - i),
            currentMonth: false
        });
    }
    
    // Días mes actual
    for (let i = 1; i <= daysInMonth; i++) {
        dateArray.push({
            date: new Date(year, month, i),
            currentMonth: true
        });
    }
    
    // Días mes siguiente hasta completar 42 celdas (6 semanas)
    const remainingSlots = 42 - dateArray.length;
    for (let i = 1; i <= remainingSlots; i++) {
        dateArray.push({
            date: new Date(year, month + 1, i),
            currentMonth: false
        });
    }
    
    return dateArray;
});

const selectDate = (date, close) => {
    emit('update:modelValue', formatDate(date));
    close();
};

const prevMonth = () => {
    viewDate.value = new Date(currentYear.value, currentMonth.value - 1, 1);
};

const nextMonth = () => {
    viewDate.value = new Date(currentYear.value, currentMonth.value + 1, 1);
};

const isSelected = (date) => {
    if (!selectedDate.value) return false;
    return date.toDateString() === selectedDate.value.toDateString();
};

const isToday = (date) => {
    return date.toDateString() === new Date().toDateString();
};

const displayValue = computed(() => {
    if (!selectedDate.value) return '';
    return selectedDate.value.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
});
</script>

<template>
    <Popover v-slot="{ open }" class="relative w-full">
        <div class="relative group">
            <PopoverButton as="div" class="w-full">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer"
                    :class="{ 'ring-2 ring-indigo-500 border-indigo-500': open }"
                >
                    <div class="flex items-center gap-2 truncate">
                        <CalendarDaysIcon class="h-5 w-5 text-gray-400 group-hover:text-indigo-500" />
                        <span :class="modelValue ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-400'">
                            {{ displayValue || placeholder }}
                        </span>
                    </div>
                </button>
            </PopoverButton>
            
            <!-- Botón de limpiar si hay fecha -->
            <button 
                v-if="modelValue" 
                @click.stop="emit('update:modelValue', '')"
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors z-10"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
        >
            <PopoverPanel
                v-slot="{ close }"
                class="absolute z-50 mt-2 w-72 transform -translate-x-1/2 left-1/2 sm:left-auto sm:right-0 sm:translate-x-0"
            >
                <div class="overflow-hidden rounded-xl shadow-2xl ring-1 ring-black ring-opacity-5 bg-white dark:bg-gray-800 border dark:border-gray-700">
                    <!-- Cabecera -->
                    <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <button @click="prevMonth" type="button" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-gray-600 dark:text-gray-400">
                            <ChevronLeftIcon class="h-5 w-5" />
                        </button>
                        <div class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">
                            {{ monthNames[currentMonth] }} {{ currentYear }}
                        </div>
                        <button @click="nextMonth" type="button" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-gray-600 dark:text-gray-400">
                            <ChevronRightIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Cuadrícula de días -->
                    <div class="p-3">
                        <div class="grid grid-cols-7 mb-2">
                            <div v-for="day in daysOfWeek" :key="day" class="text-center text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase">
                                {{ day }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <button
                                v-for="(item, idx) in days"
                                :key="idx"
                                @click="selectDate(item.date, close)"
                                type="button"
                                class="h-8 w-8 flex items-center justify-center rounded-lg text-xs transition-all relative"
                                :class="[
                                    item.currentMonth ? 'text-gray-700 dark:text-gray-200' : 'text-gray-300 dark:text-gray-600 opacity-50',
                                    isSelected(item.date) ? 'bg-indigo-600 text-white font-bold scale-105 shadow-md' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400',
                                    isToday(item.date) && !isSelected(item.date) ? 'text-indigo-600 dark:text-indigo-400 font-bold ring-1 ring-inset ring-indigo-200 dark:ring-indigo-800' : ''
                                ]"
                            >
                                {{ item.date.getDate() }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Pie de página -->
                    <div class="px-4 py-2.5 border-t dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                        <button @click="viewDate = new Date();" type="button" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline uppercase tracking-wider">Hoy</button>
                        <button @click="emit('update:modelValue', ''); close();" type="button" class="text-[10px] font-bold text-gray-500 dark:text-gray-400 hover:underline uppercase tracking-wider">Limpiar</button>
                    </div>
                </div>
            </PopoverPanel>
        </transition>
    </Popover>
</template>
