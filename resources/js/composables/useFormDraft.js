import { nextTick, onMounted, watch } from 'vue';
import { debounce } from 'lodash';

const clone = (value) => JSON.parse(JSON.stringify(value ?? null));

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

    window.sessionStorage.setItem(key, JSON.stringify({
        data: payload.data,
        extra: payload.extra ?? {},
        savedAt: new Date().toISOString(),
    }));
};

const removeStoredDraft = (key) => {
    if (typeof window === 'undefined') return;

    window.sessionStorage.removeItem(key);
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

const applyFormData = (form, data) => {
    if (!data || typeof data !== 'object') return;

    Object.entries(data).forEach(([field, value]) => {
        if (field in form) {
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
        enabled = true,
    } = options;

    let restored = false;

    const currentPayload = () => ({
        data: getFormData(form, fields),
        extra: typeof extra === 'function' ? clone(extra()) : {},
    });

    const saveDraft = () => {
        if (!enabled || !restored) return;

        writeStoredDraft(key, currentPayload());
    };

    const clearDraft = () => removeStoredDraft(key);

    onMounted(async () => {
        const stored = enabled ? readStoredDraft(key) : null;

        if (stored?.data) {
            applyFormData(form, stored.data);
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
    });

    watch(
        () => currentPayload(),
        debounce(saveDraft, debounceMs),
        { deep: true }
    );

    return {
        clearDraft,
        saveDraft,
    };
}
