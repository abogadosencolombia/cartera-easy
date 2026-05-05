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
const floatingStyles = ref({});

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

const updateFloatingPosition = async () => {
    if (!props.teleport || !open.value || !triggerRef.value) return;

    await nextTick();

    const rect = triggerRef.value.getBoundingClientRect();
    const menuWidth = menuRef.value?.offsetWidth || widthPixels.value;
    const menuHeight = menuRef.value?.offsetHeight || 0;
    const gap = 8;
    const viewportPadding = 8;

    let left = rect.left;
    if (props.align === 'right') {
        left = rect.right - menuWidth;
    } else if (props.align !== 'left') {
        left = rect.left + (rect.width / 2) - (menuWidth / 2);
    }

    left = Math.max(viewportPadding, Math.min(left, window.innerWidth - menuWidth - viewportPadding));

    let top = rect.bottom + gap;
    if (menuHeight && top + menuHeight > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, rect.top - menuHeight - gap);
    }

    floatingStyles.value = {
        left: `${left}px`,
        top: `${top}px`,
    };
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

        <Teleport v-if="teleport" to="body">
            <div
                v-show="open"
                class="fixed inset-0 z-[9998]"
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
                <div
                    v-show="open"
                    ref="menuRef"
                    class="rounded-md shadow-lg"
                    :class="[widthClass, teleport ? 'fixed z-[9999]' : ['absolute z-50 mt-2', alignmentClasses]]"
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
