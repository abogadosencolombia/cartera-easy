import { nextTick, onBeforeUnmount, onMounted, watch } from 'vue';
import { debounce } from 'lodash';
import { usePage } from '@inertiajs/vue3';

const clone = (value) => JSON.parse(JSON.stringify(value ?? null));
let legacyDraftsPurged = false;

const purgeLegacyDrafts = () => {
    if (typeof window === 'undefined' || legacyDraftsPurged) return;

    legacyDraftsPurged = true;

    try {
        const legacyKeys = [];

        for (let index = 0; index < window.sessionStorage.length; index++) {
            const storedKey = window.sessionStorage.key(index);
            if (storedKey?.startsWith('draft:') && !storedKey.includes(':user:')) {
                legacyKeys.push(storedKey);
            }
        }

        legacyKeys.forEach(storedKey => window.sessionStorage.removeItem(storedKey));
    } catch {
        // El almacenamiento puede estar deshabilitado por la política del navegador.
    }
};

const readStoredDraft = (key) => {
    if (typeof window === 'undefined') return null;

    try {
        return JSON.parse(window.sessionStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
};

const writeStoredDraft = (key, payload) => {
    if (typeof window === 'undefined') return;

    try {
        window.sessionStorage.setItem(key, JSON.stringify({
            data: payload.data,
            extra: payload.extra ?? {},
            savedAt: new Date().toISOString(),
        }));
    } catch {
        // El formulario debe seguir funcionando aunque el navegador bloquee
        // sessionStorage o no tenga espacio disponible.
    }
};

const removeStoredDraft = (key) => {
    if (typeof window === 'undefined') return;

    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Sin almacenamiento disponible no hay borrador que limpiar.
    }
};

const getFormData = (form, fields = null) => {
    if (Array.isArray(fields)) {
        return fields.reduce((data, field) => {
            data[field] = clone(form[field]);
            return data;
        }, {});
    }

    if (typeof form.data === 'function') {
        return clone(form.data());
    }

    return {};
};

const applyFormData = (form, data, fields = null) => {
    if (!data || typeof data !== 'object') return;

    Object.entries(data).forEach(([field, value]) => {
        if ((!Array.isArray(fields) || fields.includes(field)) && field in form) {
            form[field] = value;
        }
    });
};

export function useFormDraft(form, key, options = {}) {
    const {
        fields = null,
        debounceMs = 400,
        extra = null,
        restoreExtra = null,
        restoreExtraAfterTick = false,
        onRestored = null,
        enabled = true,
    } = options;

    const authenticatedUserId = usePage().props?.auth?.user?.id;
    const hasAuthenticatedUser = authenticatedUserId !== null && authenticatedUserId !== undefined;
    const storageKey = hasAuthenticatedUser ? `${key}:user:${authenticatedUserId}` : null;
    const draftEnabled = enabled && storageKey !== null;
    let restored = false;

    const currentPayload = () => ({
        data: getFormData(form, fields),
        extra: typeof extra === 'function' ? clone(extra()) : {},
    });

    const saveDraft = () => {
        if (!draftEnabled || !restored) return;

        writeStoredDraft(storageKey, currentPayload());
    };

    const debouncedSaveDraft = debounce(saveDraft, debounceMs);

    const clearDraft = () => {
        restored = false;
        debouncedSaveDraft.cancel();
        if (storageKey) removeStoredDraft(storageKey);
        removeStoredDraft(key);
    };

    onMounted(async () => {
        // Las claves antiguas no identificaban al propietario; se eliminan para
        // impedir que otra sesión del mismo navegador recupere información ajena.
        purgeLegacyDrafts();
        removeStoredDraft(key);
        const stored = draftEnabled ? readStoredDraft(storageKey) : null;

        if (stored?.data) {
            applyFormData(form, stored.data, fields);
        }

        if (stored?.extra && typeof restoreExtra === 'function' && !restoreExtraAfterTick) {
            restoreExtra(stored.extra);
        }

        await nextTick();

        if (stored?.extra && typeof restoreExtra === 'function' && restoreExtraAfterTick) {
            restoreExtra(stored.extra);
            await nextTick();
        }

        restored = true;

        // Reescribe con la allowlist actual para purgar campos que una versión
        // anterior hubiera guardado y que ya no deban persistir.
        if (stored?.data && draftEnabled) {
            writeStoredDraft(storageKey, currentPayload());
        }

        if (typeof onRestored === 'function') {
            onRestored({ hadDraft: Boolean(stored?.data) });
        }
    });

    watch(
        () => currentPayload(),
        debouncedSaveDraft,
        { deep: true }
    );

    onBeforeUnmount(() => {
        // Conserva incluso el último cambio hecho dentro de la ventana del
        // debounce. clearDraft() desactiva esta escritura tras un envío exitoso.
        if (restored) saveDraft();
        debouncedSaveDraft.cancel();
    });

    return {
        clearDraft,
        saveDraft,
    };
}
