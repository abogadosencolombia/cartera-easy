<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
    teleport: {
        type: Boolean,
        default: false,
    },
});

const open = ref(false);
const triggerRef = ref(null);
const menuRef = ref(null);

// Inicializamos con opacidad 0 para evitar el "salto" visual en el primer renderizado
const floatingStyles = ref({
    opacity: 0,
    pointerEvents: 'none'
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const widthClass = computed(() => {
    return {
        48: 'w-48',
        64: 'w-64',
        full: 'w-full',
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    } else {
        return 'origin-top';
    }
});

const widthPixels = computed(() => {
    return {
        48: 192,
        64: 256,
        full: triggerRef.value?.offsetWidth || 192,
    }[props.width.toString()] || 192;
});

/**
 * Versión optimizada de posicionamiento que ignora las dimensiones del menú en transición.
 * Usamos widthPixels (calculado) y el rect del trigger para evitar que animaciones
 * como 'scale-95' rompan el cálculo de posición.
 */
const updateFloatingPosition = async () => {
    if (!props.teleport || !open.value || !triggerRef.value) return;

    await nextTick();
    const rect = triggerRef.value.getBoundingClientRect();
    
    // Verificación de seguridad para el renderizado inicial
    if (rect.width === 0 && rect.height === 0) {
        requestAnimationFrame(updateFloatingPosition);
        return;
    }

    const gap = 4;
    const viewportPadding = 16;
    
    // IMPORTANTE: Usamos widthPixels o el ancho del trigger directamente.
    // No leemos menuRef.value.offsetWidth porque durante la animación de entrada
    // el valor es menor (debido a scale-95) y desplazaría el menú erróneamente.
    const menuWidth = props.width === 'full' ? rect.width : widthPixels.value;

    // Cálculo horizontal basado en el trigger
    let left = rect.left;
    if (props.align === 'right') {
        left = rect.right - menuWidth;
    } else if (props.align === 'center') {
        left = rect.left + (rect.width / 2) - (menuWidth / 2);
    }

    // Ajuste de seguridad para el viewport
    left = Math.max(viewportPadding, Math.min(left, window.innerWidth - menuWidth - viewportPadding));
    
    // Posición vertical fija debajo del botón para evitar saltos por animación de altura
    let top = rect.bottom + gap;

    const styles = {
        top: `${top}px`,
        left: `${left}px`,
        position: 'fixed',
        zIndex: '10051',
        opacity: '1',
        pointerEvents: 'auto'
    };

    // Aplicamos el ancho exacto para asegurar consistencia
    styles.width = `${menuWidth}px`;

    floatingStyles.value = styles;
};

const toggleOpen = async () => {
    open.value = !open.value;
    if (open.value) {
        await updateFloatingPosition();
    }
};

const addFloatingListeners = () => {
    window.addEventListener('resize', updateFloatingPosition);
    window.addEventListener('scroll', updateFloatingPosition, true);
};

const removeFloatingListeners = () => {
    window.removeEventListener('resize', updateFloatingPosition);
    window.removeEventListener('scroll', updateFloatingPosition, true);
};

watch(open, async (isOpen) => {
    if (!props.teleport) return;

    if (isOpen) {
        addFloatingListeners();
        await updateFloatingPosition();
    } else {
        removeFloatingListeners();
        // Reseteamos el estado visual al cerrar para el próximo clic
        floatingStyles.value = {
            opacity: 0,
            pointerEvents: 'none'
        };
    }
});

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    removeFloatingListeners();
});
</script>

<template>
    <div class="relative" :class="{ 'z-[9999]': open }">
        <div ref="triggerRef" @click="toggleOpen">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div
            v-if="!teleport"
            v-show="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <!-- 
            *Contenedor del menú principal. 
            Al usar el teleport, usamos z-[10051] para mantenerlo por encima de los Modales (z-[10000]).
            También usamos 'fixed' en lugar de 'absolute' para un posicionamiento correcto sobre el body.
            Capa de superposición (overlay) en Teleport para cerrar el menú desplegable al hacer clic afuera, cuando se teletransporta al body
         -->
        <Teleport v-if="teleport" to="body">
            <div
                v-show="open"
                class="fixed inset-0 z-[10050]" 
                @click="open = false"
            ></div>
        </Teleport>

        <Teleport to="body" :disabled="!teleport">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <!-- 
                    Contenedor del menú principal.
                    Al usar el teleport (teletransportar), usamos z-[10051] para mantenerlo por encima de los Modales (z-[10000]).
                    También usamos 'fixed' en lugar de 'absolute' para un posicionamiento correcto sobre el body.
                -->
                <div
                    v-show="open"
                    ref="menuRef"
                    class="rounded-md shadow-lg"
                    :class="[widthClass, teleport ? 'fixed z-[10051]' : ['absolute z-50 mt-2', alignmentClasses]]"
                    :style="teleport ? floatingStyles : undefined"
                    @click="open = false"
                >
                    <div
                        class="rounded-md ring-1 ring-black ring-opacity-5"
                        :class="contentClasses"
                    >
                        <slot name="content" />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
