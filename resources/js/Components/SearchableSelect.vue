<script setup>
import { ref, computed } from 'vue';
import {
    Combobox, ComboboxInput, ComboboxButton,
    ComboboxOptions, ComboboxOption, TransitionRoot
} from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: [String, Number, Object],
    options: { type: Array, default: () => [] },
    featuredOptions: { type: Array, default: () => [] }, // IDs o valores sugeridos
    placeholder: { type: String, default: 'Seleccionar...' },
    labelKey: { type: String, default: 'nombre' },
    valueKey: { type: String, default: 'id' },
    disabled: { type: Boolean, default: false },
    formatLabel: { type: Function, default: (val) => val }
});

const emit = defineEmits(['update:modelValue']);
const query = ref('');

const featuredOptionsData = computed(() => 
    props.options.filter(opt => props.featuredOptions.includes(opt[props.valueKey]))
);

const filteredOptions = computed(() =>
    query.value === ''
        ? props.options
        : props.options.filter((opt) => {
            const label = props.formatLabel(opt[props.labelKey]) || '';
            return label.toLowerCase()
                .replace(/\s+/g, '')
                .includes(query.value.toLowerCase().replace(/\s+/g, ''));
        })
);

const selectFeatured = (val) => {
    emit('update:modelValue', val);
    query.value = '';
};

const selectedOption = computed(() => 
    props.options.find(opt => opt[props.valueKey] === props.modelValue) || null
);
</script>

<template>
    <Combobox :modelValue="modelValue" @update:modelValue="val => emit('update:modelValue', val)" :disabled="disabled">
        <div class="relative">
            <div class="relative w-full cursor-default overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left border border-gray-300 dark:border-gray-700 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition-all duration-200 shadow-sm"
                 :class="{ 'opacity-50 cursor-not-allowed bg-gray-50 dark:bg-gray-800': disabled }">
                <ComboboxInput
                    class="w-full border-none py-2.5 pl-10 pr-10 text-sm leading-5 text-gray-900 dark:text-gray-100 bg-transparent focus:ring-0"
                    :displayValue="() => selectedOption ? formatLabel(selectedOption[labelKey]) : ''"
                    @change="query = $event.target.value"
                    :placeholder="placeholder"
                    autocomplete="off"
                />
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" aria-hidden="true" />
                </div>
                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                    <ChevronUpDownIcon class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" aria-hidden="true" />
                </ComboboxButton>
            </div>
            
            <TransitionRoot
                leave="transition ease-in duration-100"
                leaveFrom="opacity-100"
                leaveTo="opacity-0"
                @after-leave="query = ''"
            >
                <ComboboxOptions class="absolute mt-2 max-h-80 w-full overflow-auto rounded-xl bg-white dark:bg-gray-800 py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm z-[100] border dark:border-gray-700 backdrop-blur-sm">
                    
                    <!-- SECCIÓN DE OPCIONES DESTACADAS (Quick Picks) -->
                    <div v-if="featuredOptionsData.length > 0 && query === ''" class="p-4 border-b dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="h-4 w-1 bg-indigo-500 rounded-full"></div>
                            <p class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">Opciones Frecuentes</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                v-for="opt in featuredOptionsData" 
                                :key="'quick-'+opt[valueKey]"
                                type="button"
                                @click="selectFeatured(opt[valueKey])"
                                class="flex items-center justify-center text-center px-3 py-2.5 text-[11px] font-bold rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-400 transition-all duration-150 shadow-sm"
                            >
                                {{ formatLabel(opt[labelKey]) }}
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredOptions.length === 0 && query !== ''" class="relative cursor-default select-none py-6 px-4 text-center">
                        <div class="text-gray-400 mb-1 font-bold">No hay coincidencias</div>
                        <div class="text-[10px] text-gray-500 italic">Intenta con otro término</div>
                    </div>

                    <div v-if="query === '' && featuredOptionsData.length > 0" class="px-4 py-3 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b dark:border-gray-700/50 mb-1 flex items-center gap-2">
                        <div class="h-[1px] flex-grow bg-gray-200 dark:bg-gray-700"></div>
                        <span>Otras opciones</span>
                        <div class="h-[1px] flex-grow bg-gray-200 dark:bg-gray-700"></div>
                    </div>

                    <ComboboxOption
                        v-for="option in filteredOptions"
                        :key="option[valueKey]"
                        :value="option[valueKey]"
                        v-slot="{ selected, active }"
                    >
                        <li class="relative cursor-default select-none py-2.5 pl-10 pr-4 transition-colors text-sm"
                            :class="{ 'bg-indigo-600 text-white': active, 'text-gray-900 dark:text-gray-200': !active }">
                            <span class="block truncate" :class="{ 'font-bold': selected, 'font-normal': !selected }">
                                {{ formatLabel(option[labelKey]) }}
                            </span>
                            <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3"
                                :class="{ 'text-white': active, 'text-indigo-600': !active }">
                                <CheckIcon class="h-4 w-4" aria-hidden="true" />
                            </span>
                        </li>
                    </ComboboxOption>
                </ComboboxOptions>
            </TransitionRoot>
        </div>
    </Combobox>
</template>
