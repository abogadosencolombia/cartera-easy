<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { useElementBounding, useWindowSize } from '@vueuse/core';
import axios from 'axios';
import { 
    MagnifyingGlassIcon, 
    ChevronDownIcon, 
    ArrowPathIcon, 
    XMarkIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  modelValue: { type: [Object, Array], default: null },
  endpoint: { type: String, required: true },
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Seleccione opciones...' },
  labelKey: { type: String, default: 'nombre' }, // Puede ser 'nombre', 'name', 'nombre_completo'
  clearable: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const isOpen = ref(false);
const loading = ref(false);
const container = ref(null);
const panel = ref(null);
const inheritedDisabled = ref(false);
let timeout = null;
let fetchRequest = 0;
let disabledObserver = null;

const { bottom, left, width, top } = useElementBounding(container);
const { height: windowHeight } = useWindowSize();
const isDisabled = computed(() => props.disabled || inheritedDisabled.value);

const floatingStyles = computed(() => {
    const dropdownHeight = 320; 
    const spaceBelow = windowHeight.value - bottom.value;
    const showAbove = spaceBelow < dropdownHeight && top.value > dropdownHeight;

    return {
        top: showAbove ? `${top.value - dropdownHeight - 8}px` : `${bottom.value}px`,
        left: `${left.value}px`,
        width: `${width.value}px`,
    };
});

// Normalizar el valor para mostrarlo
const displayLabel = (item) => {
    if (!item) return '';
    return item.label || item[props.labelKey] || item.name || item.nombre || item.nombre_completo;
};

// Cargar datos iniciales
onMounted(() => {
    if (props.modelValue && !Array.isArray(props.modelValue)) {
        // Si hay un valor inicial, podríamos cargar su label si fuera necesario, 
        // pero displayLabel ya lo maneja si el objeto viene completo.
    }

    const parentFieldset = container.value?.closest('fieldset');
    const updateInheritedDisabled = () => {
        inheritedDisabled.value = Boolean(parentFieldset?.disabled);
    };

    updateInheritedDisabled();
    if (parentFieldset && typeof MutationObserver !== 'undefined') {
        disabledObserver = new MutationObserver(updateInheritedDisabled);
        disabledObserver.observe(parentFieldset, { attributes: true, attributeFilter: ['disabled'] });
    }

    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    clearTimeout(timeout);
    fetchRequest++;
    disabledObserver?.disconnect();
    document.removeEventListener('click', handleClickOutside);
});

const closeDropdown = () => {
    isOpen.value = false;
    clearTimeout(timeout);
    fetchRequest++;
    loading.value = false;
};

const handleClickOutside = (e) => {
    const clickedTrigger = container.value?.contains(e.target);
    const clickedPanel = panel.value?.contains(e.target);

    if (!clickedTrigger && !clickedPanel) {
        closeDropdown();
    }
};

const fetchResults = (term, requestId = ++fetchRequest) => {
    loading.value = true;
    axios.get(props.endpoint, { params: { term } })
        .then(res => {
            if (requestId === fetchRequest) {
                results.value = Array.isArray(res.data) ? res.data : [];
            }
        })
        .catch(() => {
            if (requestId === fetchRequest) {
                results.value = [];
            }
        })
        .finally(() => {
            if (requestId === fetchRequest) {
                loading.value = false;
            }
        });
};

watch(query, (newQuery) => {
    clearTimeout(timeout);
    const requestId = ++fetchRequest;

    if (!isOpen.value) {
        loading.value = false;
        return;
    }

    timeout = setTimeout(() => fetchResults(newQuery, requestId), 300);
});

const isSelected = (item) => {
    if (!props.modelValue) return false;
    if (props.multiple) {
        return props.modelValue.some(i => i.id === item.id);
    }
    return props.modelValue.id === item.id;
};

const select = (item) => {
    if (isDisabled.value) return;

    if (props.multiple) {
        let newValue = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
        const index = newValue.findIndex(i => i.id === item.id);
        if (index > -1) {
            newValue.splice(index, 1);
        } else {
            newValue.push(item);
        }
        emit('update:modelValue', newValue);
    } else {
        emit('update:modelValue', item);
        closeDropdown();
    }
    query.value = '';
};

const removeItem = (item) => {
    if (!isDisabled.value && props.multiple && Array.isArray(props.modelValue)) {
        emit('update:modelValue', props.modelValue.filter(i => i.id !== item.id));
    }
};

const clearSelection = () => {
    if (isDisabled.value) return;

    emit('update:modelValue', props.multiple ? [] : null);
    query.value = '';
};

const toggle = () => {
    if (isDisabled.value) return;

    if (isOpen.value) {
        closeDropdown();
        return;
    }

    isOpen.value = true;
    query.value = '';
    fetchResults('');
};

watch(isDisabled, (disabled) => {
    if (disabled) closeDropdown();
});
</script>

<template>
  <div class="relative w-full" ref="container">
    <!-- Trigger Principal (Estilo Calendar/Input) -->
    <button
        type="button"
        @click="toggle"
        :disabled="isDisabled"
        :aria-expanded="isOpen"
        aria-haspopup="listbox"
        class="min-h-[42px] w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-left text-sm shadow-sm cursor-pointer hover:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all flex flex-wrap gap-1.5 items-center justify-between disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:border-gray-300"
    >
        <span class="flex flex-wrap gap-1.5 flex-1 min-w-0">
            <!-- Icono inicial si no hay nada -->
            <MagnifyingGlassIcon v-if="!multiple && !modelValue" class="h-4 w-4 text-gray-400" />
            
            <!-- Valores Seleccionados (Multiple) -->
            <template v-if="multiple && Array.isArray(modelValue) && modelValue.length > 0">
                <span v-for="item in modelValue" :key="item.id" 
                    @click.stop="removeItem(item)"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-semibold group border border-indigo-100 dark:border-indigo-800 hover:bg-red-50 hover:text-red-700 hover:border-red-200 transition-colors"
                >
                    {{ displayLabel(item) }}
                    <XMarkIcon class="h-3 w-3 opacity-50 group-hover:opacity-100" />
                </span>
            </template>

            <!-- Valor Seleccionado (Simple) -->
            <span v-else-if="!multiple && modelValue" class="text-gray-900 dark:text-gray-100 font-medium truncate">
                {{ displayLabel(modelValue) }}
            </span>

            <!-- Placeholder -->
            <span v-else class="text-gray-400 dark:text-gray-500 truncate">
                {{ placeholder }}
            </span>
        </span>
        
        <span class="flex items-center gap-2 shrink-0 ml-2">
            <ArrowPathIcon v-if="loading" class="h-4 w-4 text-indigo-500 animate-spin" />
            
            <!-- Botón para limpiar (Simple) -->
            <XMarkIcon 
                v-if="!multiple && modelValue && clearable" 
                @click.stop="clearSelection" 
                class="h-4 w-4 text-gray-400 hover:text-red-500 transition-colors"
                title="Quitar selección"
            />

            <ChevronDownIcon class="h-4 w-4 text-gray-400 transition-transform duration-300" :class="{'rotate-180': isOpen}" />
        </span>
    </button>

    <!-- Panel Dropdown Premium -->
    <Teleport to="body">
        <div 
            v-if="isOpen" 
            ref="panel"
            @click.stop
            class="fixed z-[9999] mt-1 rounded-xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-in fade-in zoom-in-95 duration-200 origin-top"
            :style="floatingStyles"
        >
            <!-- Buscador interno -->
            <div class="p-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="relative">
                    <input 
                        v-model="query"
                        type="text"
                        :disabled="isDisabled"
                        class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border-gray-200 dark:border-gray-600 dark:bg-gray-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        placeholder="Escribe para filtrar..."
                        @click.stop
                        autoFocus
                    />
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                </div>
            </div>

            <!-- Lista de Resultados -->
            <ul class="max-h-64 overflow-y-auto py-2 scrollbar-thin scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-700">
                <li v-if="results.length === 0 && !loading" class="px-4 py-8 text-sm text-center text-gray-500 italic">
                    <div class="flex flex-col items-center">
                        <MagnifyingGlassIcon class="h-8 w-8 text-gray-300 mb-2" />
                        No se encontraron coincidencias
                    </div>
                </li>
                
                <li 
                    v-for="item in results" 
                    :key="item.id"
                    @click="select(item)"
                    class="px-4 py-2.5 text-sm cursor-pointer transition-all flex items-center justify-between group mx-2 rounded-lg mb-0.5"
                    :class="isSelected(item) 
                        ? 'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold' 
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'"
                >
                    <div class="flex flex-col min-w-0">
                        <span class="truncate">{{ displayLabel(item) }}</span>
                        <span v-if="item.numero_documento" class="text-[10px] opacity-50 font-mono tracking-tighter">{{ item.numero_documento }}</span>
                    </div>
                    
                    <CheckIcon v-if="isSelected(item)" class="h-4 w-4 text-indigo-600 shrink-0" />
                </li>
            </ul>
            
            <!-- Footer Info si es multiple -->
            <div v-if="multiple && Array.isArray(modelValue) && modelValue.length > 0" class="p-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/20 text-[10px] text-center text-gray-500">
                {{ modelValue.length }} elemento(s) seleccionado(s)
            </div>
        </div>
    </Teleport>
  </div>
</template>
