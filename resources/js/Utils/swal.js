import SweetAlert from 'sweetalert2';

const baseCustomClass = {
    popup: 'cc-swal-popup',
    title: 'cc-swal-title',
    htmlContainer: 'cc-swal-html',
    actions: 'cc-swal-actions',
    confirmButton: 'cc-swal-button cc-swal-confirm',
    denyButton: 'cc-swal-button cc-swal-deny',
    cancelButton: 'cc-swal-button cc-swal-cancel',
    closeButton: 'cc-swal-close',
    icon: 'cc-swal-icon',
    timerProgressBar: 'cc-swal-progress',
};

const baseShowClass = {
    popup: '',
};

const baseHideClass = {
    popup: '',
};

const baseDefaults = {
    buttonsStyling: false,
    scrollbarPadding: false,
};

const classList = (...values) => values.filter(Boolean).join(' ');

const iconClass = (icon) => {
    const supportedIcons = ['success', 'error', 'warning', 'info', 'question'];
    return supportedIcons.includes(icon) ? `cc-swal-${icon}` : '';
};

const iconMarkup = (icon) => {
    const icons = {
        success: '<svg class="cc-swal-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
        error: '<svg class="cc-swal-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        warning: '<svg class="cc-swal-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 17h.01"/></svg>',
        info: '<svg class="cc-swal-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 10v7M12 7h.01"/></svg>',
        question: '<svg class="cc-swal-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 9a2.5 2.5 0 1 1 4.06 1.95c-.8.62-1.56 1.12-1.56 2.3M12 17h.01"/></svg>',
    };

    return icons[icon] || undefined;
};

const popupMotionClass = (options, type) => {
    if (options[type]?.popup) {
        return options[type];
    }

    return {
        popup: options.toast
            ? (type === 'showClass' ? 'cc-swal-toast-show' : 'cc-swal-toast-hide')
            : (type === 'showClass' ? 'cc-swal-modal-show' : 'cc-swal-modal-hide'),
    };
};

const mergeClassMap = (baseClasses, incomingClasses = {}, extraPopupClass = '') => {
    if (typeof incomingClasses === 'string') {
        return {
            ...baseClasses,
            popup: classList(baseClasses.popup, incomingClasses, extraPopupClass),
        };
    }

    return Object.keys({ ...baseClasses, ...incomingClasses }).reduce((classes, key) => {
        classes[key] = classList(
            baseClasses[key],
            incomingClasses?.[key],
            key === 'popup' ? extraPopupClass : ''
        );
        return classes;
    }, {});
};

const withAppDefaults = (options = {}) => {
    const normalized = {
        ...baseDefaults,
        ...options,
    };

    if (normalized.toast) {
        normalized.position ??= 'top-end';
        normalized.showConfirmButton ??= false;
        normalized.timer ??= 3800;
        normalized.timerProgressBar ??= true;
        delete normalized.heightAuto;
    } else {
        normalized.heightAuto ??= false;
    }

    if (normalized.showCancelButton) {
        normalized.confirmButtonText ??= 'Confirmar';
        normalized.cancelButtonText ??= 'Cancelar';
        normalized.reverseButtons ??= true;
    } else {
        normalized.confirmButtonText ??= 'Aceptar';
    }

    if (normalized.icon && !normalized.iconHtml) {
        normalized.iconHtml = iconMarkup(normalized.icon);
    }

    delete normalized.confirmButtonColor;
    delete normalized.cancelButtonColor;
    delete normalized.denyButtonColor;

    const userDidOpen = normalized.didOpen;
    normalized.didOpen = (popup) => {
        if (normalized.toast) {
            popup.onmouseenter = SweetAlert.stopTimer;
            popup.onmouseleave = SweetAlert.resumeTimer;
        }

        userDidOpen?.(popup);
    };

    const showClass = popupMotionClass(options, 'showClass');
    const hideClass = popupMotionClass(options, 'hideClass');

    return {
        ...normalized,
        customClass: mergeClassMap(baseCustomClass, options.customClass, iconClass(normalized.icon)),
        showClass: mergeClassMap(baseShowClass, showClass),
        hideClass: mergeClassMap(baseHideClass, hideClass),
    };
};

const legacyArgsToOptions = (args) => {
    if (typeof args[0] !== 'string') {
        return null;
    }

    return {
        title: args[0],
        text: args[1] || '',
        icon: args[2],
    };
};

const isOptionsObject = (args) => args.length === 1 && args[0] && typeof args[0] === 'object';

const Swal = SweetAlert.mixin(baseDefaults);
const rootFire = Swal.fire.bind(Swal);
const rootMixin = Swal.mixin.bind(Swal);

Swal.fire = (...args) => {
    if (isOptionsObject(args)) {
        return rootFire(withAppDefaults(args[0]));
    }

    const legacyOptions = legacyArgsToOptions(args);
    if (legacyOptions) {
        return rootFire(withAppDefaults(legacyOptions));
    }

    return rootFire(...args);
};

Swal.mixin = (options = {}) => {
    const mixinOptions = { ...options };
    const instance = rootMixin(withAppDefaults(mixinOptions));
    const instanceFire = instance.fire.bind(instance);

    instance.fire = (...args) => {
        if (isOptionsObject(args)) {
            return instanceFire(withAppDefaults({ ...mixinOptions, ...args[0] }));
        }

        const legacyOptions = legacyArgsToOptions(args);
        if (legacyOptions) {
            return instanceFire(withAppDefaults({ ...mixinOptions, ...legacyOptions }));
        }

        return instanceFire(...args);
    };

    return instance;
};

if (typeof window !== 'undefined') {
    window.Swal = Swal;
}

export default Swal;
