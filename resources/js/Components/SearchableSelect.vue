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
                <ComboboxOptions class="absolute mt-2 max-h-60 w-full overflow-auto rounded-xl bg-white dark:bg-gray-800 py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm z-[100] border dark:border-gray-700 backdrop-blur-sm">
                    
                    <!-- SECCIÓN DE OPCIONES DESTACADAS (Quick Picks) -->
                    <div v-if="featuredOptionsData.length > 0 && query === ''" class="p-3 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                        <p class="text-[10px] font-bold text-gray-400 uppercase px-1 mb-2 tracking-widest">Sugeridos</p>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                v-for="opt in featuredOptionsData" 
                                :key="'quick-'+opt[valueKey]"
                                type="button"
                                @click="selectFeatured(opt[valueKey])"
                                class="px-3 py-1 text-xs font-semibold rounded-full bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500 dark:hover:text-white transition-all shadow-sm"
                            >
                                {{ formatLabel(opt[labelKey]) }}
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredOptions.length === 0 && query !== ''" class="relative cursor-default select-none py-4 px-4 text-gray-500 italic text-sm">
                        No se encontraron resultados para "{{ query }}"
                    </div>

                    <div v-if="query === '' && featuredOptionsData.length > 0" class="px-4 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b dark:border-gray-700 mb-1">
                        Todos los registros
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
