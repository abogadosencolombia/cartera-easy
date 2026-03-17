<script setup>
import { ref, computed, watch } from 'vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { 
    ChevronLeftIcon, 
    ChevronRightIcon, 
    CalendarDaysIcon, 
    XMarkIcon, 
    ChevronDownIcon,
    ClockIcon
} from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    placeholder: { type: String, default: 'Seleccionar fecha y hora' }
});

const emit = defineEmits(['update:modelValue']);

// Constantes
const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const daysOfWeek = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'];

// Estados
const hours = ref('12');
const minutes = ref('00');
const showHourDropdown = ref(false);
const showMinuteDropdown = ref(false);

const hourOptions = Array.from({ length: 24 }, (_, i) => i.toString().padStart(2, '0'));
const minuteOptions = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];

// Lógica de parseo
const parseDateTime = (dateTimeStr) => {
    if (!dateTimeStr) return { date: null, hours: '12', minutes: '00' };
    const normalized = dateTimeStr.replace('T', ' ');
    const [datePart, timePart] = normalized.split(' ');
    const [year, month, day] = datePart.split('-').map(Number);
    const dateObj = new Date(year, month - 1, day);
    let h = '12', m = '00';
    if (timePart) {
        const [hour, min] = timePart.split(':');
        h = hour.padStart(2, '0');
        m = min.padStart(2, '0');
    }
    return { date: dateObj, hours: h, minutes: m };
};

const initial = parseDateTime(props.modelValue);
const selectedDate = ref(initial.date);
hours.value = initial.hours;
minutes.value = initial.minutes;

const viewDate = ref(selectedDate.value || new Date());
const currentMonth = computed(() => viewDate.value.getMonth());
const currentYear = computed(() => viewDate.value.getFullYear());

watch(() => props.modelValue, (newVal) => {
    const parsed = parseDateTime(newVal);
    selectedDate.value = parsed.date;
    hours.value = parsed.hours;
    minutes.value = parsed.minutes;
});

const emitValue = () => {
    if (!selectedDate.value) return;
    const year = selectedDate.value.getFullYear();
    const month = (selectedDate.value.getMonth() + 1).toString().padStart(2, '0');
    const day = selectedDate.value.getDate().toString().padStart(2, '0');
    emit('update:modelValue', `${year}-${month}-${day} ${hours.value}:${minutes.value}:00`);
};

const days = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const dateArray = [];
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = firstDay - 1; i >= 0; i--) dateArray.push({ date: new Date(year, month - 1, prevMonthLastDay - i), currentMonth: false });
    for (let i = 1; i <= daysInMonth; i++) dateArray.push({ date: new Date(year, month, i), currentMonth: true });
    const remaining = 42 - dateArray.length;
    for (let i = 1; i <= remaining; i++) dateArray.push({ date: new Date(year, month + 1, i), currentMonth: false });
    return dateArray;
});

const handleDateSelect = (date) => {
    selectedDate.value = date;
    emitValue();
};

const setHour = (h) => { hours.value = h; showHourDropdown.value = false; emitValue(); };
const setMinute = (m) => { minutes.value = m; showMinuteDropdown.value = false; emitValue(); };

const displayValue = computed(() => {
    if (!selectedDate.value) return '';
    const dateStr = selectedDate.value.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
    return `${dateStr} - ${hours.value}:${minutes.value}`;
});
</script>

<template>
    <Popover v-slot="{ open }" class="relative w-full">
        <div class="relative group">
            <PopoverButton as="div" class="w-full">
                <button type="button" class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-xs shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer min-h-[42px]" :class="[open ? 'ring-2 ring-indigo-500 border-indigo-500' : '', modelValue ? 'pr-9' : '']">
                    <div class="flex items-center gap-2 truncate">
                        <CalendarDaysIcon class="h-4 w-4 text-gray-400 group-hover:text-indigo-500" />
                        <span :class="modelValue ? 'text-gray-900 dark:text-white font-bold' : 'text-gray-400'">{{ displayValue || placeholder }}</span>
                    </div>
                </button>
            </PopoverButton>
            <button v-if="modelValue" @click.stop="emit('update:modelValue', '')" type="button" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-red-500 transition-colors z-10"><XMarkIcon class="h-4 w-4" /></button>
        </div>

        <transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-1 opacity-0">
            <PopoverPanel v-slot="{ close }" class="absolute z-[100] mt-2 w-72 transform -translate-x-1/2 left-1/2 sm:left-auto sm:right-0 sm:translate-x-0">
                <div class="overflow-visible rounded-xl shadow-2xl ring-1 ring-black ring-opacity-5 bg-white dark:bg-gray-800 border dark:border-gray-700">
                    
                    <!-- Header -->
                    <div class="px-2 py-3 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between rounded-t-xl">
                        <button @click="viewDate = new Date(currentYear, currentMonth - 1, 1)" type="button" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-gray-600"><ChevronLeftIcon class="h-4 w-4" /></button>
                        <span class="text-[10px] font-black uppercase text-gray-900 dark:text-white tracking-widest">{{ monthNames[currentMonth] }} {{ currentYear }}</span>
                        <button @click="viewDate = new Date(currentYear, currentMonth + 1, 1)" type="button" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-gray-600"><ChevronRightIcon class="h-4 w-4" /></button>
                    </div>

                    <!-- Calendario -->
                    <div class="p-3">
                        <div class="grid grid-cols-7 mb-2">
                            <div v-for="day in daysOfWeek" :key="day" class="text-center text-[10px] font-black text-gray-400 uppercase">{{ day }}</div>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <button v-for="(item, idx) in days" :key="idx" @click="handleDateSelect(item.date)" type="button" class="h-8 w-8 flex items-center justify-center rounded-lg text-xs transition-all relative" :class="[
                                item.currentMonth ? 'text-gray-700 dark:text-gray-200' : 'text-gray-300 dark:text-gray-600 opacity-50',
                                item.date.toDateString() === (selectedDate?.toDateString()) ? 'bg-indigo-600 text-white font-bold' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30',
                                item.date.toDateString() === new Date().toDateString() && !isSelected ? 'ring-1 ring-indigo-500' : ''
                            ]">{{ item.date.getDate() }}</button>
                        </div>
                    </div>

                    <!-- Selector de Tiempo CORREGIDO -->
                    <div class="px-4 py-3 border-t dark:border-gray-700 bg-indigo-50/30 dark:bg-indigo-900/10 relative">
                        <div class="flex items-center justify-center gap-4">
                            <ClockIcon class="h-4 w-4 text-indigo-500 shrink-0" />
                            
                            <div class="flex items-center gap-2">
                                <!-- Botón Hora -->
                                <div class="relative">
                                    <button @click="showHourDropdown = !showHourDropdown; showMinuteDropdown = false" type="button" class="w-12 py-1.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[11px] font-bold text-gray-900 dark:text-white hover:border-indigo-500 transition-colors flex items-center justify-center gap-1">
                                        {{ hours }}
                                        <ChevronDownIcon class="h-3 w-3 opacity-40" />
                                    </button>
                                    
                                    <!-- Dropdown Hora (Hacia ARRIBA para no empujar el layout) -->
                                    <div v-if="showHourDropdown" class="absolute bottom-full mb-2 left-0 w-12 max-h-40 overflow-y-auto bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg shadow-xl z-[120] custom-scrollbar">
                                        <button v-for="h in hourOptions" :key="h" @click="setHour(h)" class="w-full py-1.5 text-[10px] font-bold hover:bg-indigo-600 hover:text-white transition-colors border-b dark:border-gray-700 last:border-0" :class="hours === h ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300'">{{ h }}</button>
                                    </div>
                                </div>

                                <span class="text-gray-400 font-black">:</span>

                                <!-- Botón Minuto -->
                                <div class="relative">
                                    <button @click="showMinuteDropdown = !showMinuteDropdown; showHourDropdown = false" type="button" class="w-12 py-1.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[11px] font-bold text-gray-900 dark:text-white hover:border-indigo-500 transition-colors flex items-center justify-center gap-1">
                                        {{ minutes }}
                                        <ChevronDownIcon class="h-3 w-3 opacity-40" />
                                    </button>
                                    
                                    <!-- Dropdown Minuto (Hacia ARRIBA) -->
                                    <div v-if="showMinuteDropdown" class="absolute bottom-full mb-2 left-0 w-12 max-h-40 overflow-y-auto bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg shadow-xl z-[120] custom-scrollbar">
                                        <button v-for="m in minuteOptions" :key="m" @click="setMinute(m)" class="w-full py-1.5 text-[10px] font-bold hover:bg-indigo-600 hover:text-white transition-colors border-b dark:border-gray-700 last:border-0" :class="minutes === m ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300'">{{ m }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-4 py-2 border-t dark:border-gray-700 flex justify-between bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
                        <button @click="handleDateSelect(new Date()); close();" type="button" class="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Hoy</button>
                        <button @click="close()" type="button" class="text-[10px] font-bold text-gray-500 uppercase hover:underline">Cerrar</button>
                    </div>
                </div>
            </PopoverPanel>
        </transition>
    </Popover>
</template>

<style scoped>
/* Scrollbar ultra-fino y elegante */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #475569;
}
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
</style>
