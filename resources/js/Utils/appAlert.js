const ICONS = {
    success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 17h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 10v7M12 7h.01"/></svg>',
    question: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 9a2.5 2.5 0 1 1 4.06 1.95c-.8.62-1.56 1.12-1.56 2.3M12 17h.01"/></svg>',
};

const SUPPORTED_ICONS = Object.keys(ICONS);
const DEFAULTS = {
    icon: 'info',
    position: 'top-end',
    timer: 3800,
    confirmButtonText: 'Aceptar',
    cancelButtonText: 'Cancelar',
    denyButtonText: 'No',
};

let modalCount = 0;

const isBrowser = () => typeof window !== 'undefined' && typeof document !== 'undefined';

const normalizeIcon = (icon) => SUPPORTED_ICONS.includes(icon) ? icon : 'info';

const parseArgs = (args) => {
    if (args.length === 1 && args[0] && typeof args[0] === 'object') {
        return { ...args[0] };
    }

    if (typeof args[0] === 'string') {
        return {
            title: args[0],
            text: args[1] || '',
            icon: args[2] || 'info',
        };
    }

    return {};
};

const mergeOptions = (defaults, incoming) => {
    const options = {
        ...DEFAULTS,
        ...defaults,
        ...incoming,
    };

    options.icon = normalizeIcon(options.icon);
    options.confirmButtonText = options.confirmButtonText || DEFAULTS.confirmButtonText;
    options.cancelButtonText = options.cancelButtonText || DEFAULTS.cancelButtonText;

    return options;
};

const injectStyles = () => {
    if (!isBrowser() || document.getElementById('cc-app-alert-styles')) return;

    const style = document.createElement('style');
    style.id = 'cc-app-alert-styles';
    style.textContent = `
        .cc-app-toast-region {
            position: fixed;
            z-index: 99999;
            display: grid;
            width: min(92vw, 26rem);
            gap: .75rem;
            pointer-events: none;
        }
        .cc-app-toast-region[data-position="top-end"] { top: 1rem; right: 1rem; }
        .cc-app-toast-region[data-position="top-start"] { top: 1rem; left: 1rem; }
        .cc-app-toast-region[data-position="bottom-end"] { right: 1rem; bottom: 1rem; }
        .cc-app-toast-region[data-position="bottom-start"] { left: 1rem; bottom: 1rem; }
        .cc-app-toast {
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: start;
            padding: .95rem 1rem;
            border: 1px solid rgb(226 232 240);
            border-left-width: 4px;
            border-radius: 8px;
            background: rgb(255 255 255);
            color: rgb(15 23 42);
            box-shadow: 0 16px 42px rgb(15 23 42 / .16);
            pointer-events: auto;
            animation: cc-app-toast-in 180ms ease-out both;
        }
        .cc-app-toast-progress {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 3px;
            border-radius: 9999px;
            background: rgb(37 99 235 / .32);
            transform-origin: left;
            animation: cc-app-progress-shrink linear forwards;
        }
        .cc-app-alert-success .cc-app-toast-progress { background: rgb(5 150 105 / .35); }
        .cc-app-alert-error .cc-app-toast-progress { background: rgb(220 38 38 / .35); }
        .cc-app-alert-warning .cc-app-toast-progress { background: rgb(217 119 6 / .35); }
        .cc-app-toast.cc-app-alert-leaving { animation: cc-app-toast-out 140ms ease-in both; }
        .cc-app-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 99998;
            display: grid;
            place-items: center;
            padding: 1rem;
            background: rgb(15 23 42 / .48);
            animation: cc-app-fade-in 130ms ease-out both;
        }
        .cc-app-modal-backdrop.cc-app-alert-leaving { animation: cc-app-fade-out 120ms ease-in both; }
        .cc-app-modal {
            width: min(92vw, 30rem);
            overflow: hidden;
            padding: 1.35rem;
            border: 1px solid rgb(226 232 240);
            border-top-width: 4px;
            border-radius: 8px;
            background: linear-gradient(180deg, rgb(255 255 255), rgb(248 250 252));
            color: rgb(15 23 42);
            box-shadow: 0 22px 65px rgb(15 23 42 / .22);
            animation: cc-app-modal-in 160ms ease-out both;
        }
        .cc-app-modal-backdrop.cc-app-alert-leaving .cc-app-modal { animation: cc-app-modal-out 125ms ease-in both; }
        .cc-app-alert-icon {
            display: inline-flex;
            width: 2.2rem;
            height: 2.2rem;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: rgb(219 234 254);
            color: rgb(37 99 235);
            flex: 0 0 auto;
        }
        .cc-app-modal .cc-app-alert-icon {
            width: 3rem;
            height: 3rem;
            margin-bottom: .95rem;
        }
        .cc-app-alert-icon svg {
            width: 1.3rem;
            height: 1.3rem;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.35;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .cc-app-modal .cc-app-alert-icon svg { width: 1.55rem; height: 1.55rem; }
        .cc-app-alert-success { border-left-color: rgb(5 150 105); border-top-color: rgb(5 150 105); }
        .cc-app-alert-error { border-left-color: rgb(220 38 38); border-top-color: rgb(220 38 38); }
        .cc-app-alert-warning { border-left-color: rgb(217 119 6); border-top-color: rgb(217 119 6); }
        .cc-app-alert-info, .cc-app-alert-question { border-left-color: rgb(37 99 235); border-top-color: rgb(37 99 235); }
        .cc-app-alert-success .cc-app-alert-icon { background: rgb(220 252 231); color: rgb(5 150 105); }
        .cc-app-alert-error .cc-app-alert-icon { background: rgb(254 226 226); color: rgb(220 38 38); }
        .cc-app-alert-warning .cc-app-alert-icon { background: rgb(254 243 199); color: rgb(217 119 6); }
        .cc-app-alert-title {
            margin: 0;
            color: rgb(15 23 42);
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.35;
            letter-spacing: 0;
        }
        .cc-app-toast .cc-app-alert-title { font-size: .875rem; line-height: 1.3; }
        .cc-app-alert-body {
            margin-top: .5rem;
            color: rgb(71 85 105);
            font-size: .9rem;
            line-height: 1.5;
        }
        .cc-app-toast .cc-app-alert-body {
            margin-top: .2rem;
            font-size: .8125rem;
            line-height: 1.4;
        }
        .cc-app-alert-close {
            display: inline-flex;
            width: 1.75rem;
            height: 1.75rem;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: rgb(100 116 139);
            cursor: pointer;
        }
        .cc-app-alert-close:hover { background: rgb(241 245 249); color: rgb(15 23 42); }
        .cc-app-alert-actions {
            display: flex;
            justify-content: flex-end;
            gap: .625rem;
            margin-top: 1.1rem;
        }
        .cc-app-alert-button {
            display: inline-flex;
            min-height: 2.5rem;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: .625rem .95rem;
            font-size: .875rem;
            font-weight: 800;
            line-height: 1.2;
            cursor: pointer;
            transition: background-color 140ms ease, border-color 140ms ease, color 140ms ease, transform 140ms ease;
        }
        .cc-app-alert-button:hover { transform: translateY(-1px); }
        .cc-app-alert-button:focus {
            outline: 2px solid rgb(59 130 246 / .35);
            outline-offset: 2px;
        }
        .cc-app-alert-confirm {
            border: 1px solid rgb(37 99 235);
            background: rgb(37 99 235);
            color: white;
        }
        .cc-app-alert-confirm:hover { border-color: rgb(29 78 216); background: rgb(29 78 216); }
        .cc-app-alert-cancel {
            border: 1px solid rgb(203 213 225);
            background: white;
            color: rgb(51 65 85);
        }
        .cc-app-alert-cancel:hover { border-color: rgb(148 163 184); background: rgb(248 250 252); }
        .cc-app-alert-deny {
            border: 1px solid rgb(220 38 38);
            background: rgb(220 38 38);
            color: white;
        }
        .cc-app-alert-deny:hover { border-color: rgb(185 28 28); background: rgb(185 28 28); }
        .dark .cc-app-toast,
        .dark .cc-app-modal {
            border-color: rgb(51 65 85);
            background: linear-gradient(180deg, rgb(15 23 42), rgb(30 41 59));
            color: rgb(248 250 252);
            box-shadow: 0 18px 55px rgb(0 0 0 / .36);
        }
        .dark .cc-app-alert-title { color: rgb(248 250 252); }
        .dark .cc-app-alert-body { color: rgb(203 213 225); }
        .dark .cc-app-alert-close { color: rgb(148 163 184); }
        .dark .cc-app-alert-close:hover { background: rgb(51 65 85); color: rgb(248 250 252); }
        .dark .cc-app-alert-cancel {
            border-color: rgb(71 85 105);
            background: rgb(30 41 59);
            color: rgb(226 232 240);
        }
        .dark .cc-app-alert-cancel:hover { border-color: rgb(100 116 139); background: rgb(51 65 85); }
        @media (max-width: 480px) {
            .cc-app-toast-region { right: .75rem; left: .75rem; width: auto; }
            .cc-app-alert-actions { flex-direction: column-reverse; }
            .cc-app-alert-button { width: 100%; }
        }
        @keyframes cc-app-modal-in {
            from { opacity: 0; transform: translateY(8px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes cc-app-modal-out {
            from { opacity: 1; transform: translateY(0) scale(1); }
            to { opacity: 0; transform: translateY(6px) scale(.98); }
        }
        @keyframes cc-app-toast-in {
            from { opacity: 0; transform: translateX(16px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes cc-app-toast-out {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(12px); }
        }
        @keyframes cc-app-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes cc-app-fade-out {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @keyframes cc-app-progress-shrink {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    `;
    document.head.appendChild(style);
};

const element = (tag, className, text = null) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null && text !== undefined) node.textContent = text;
    return node;
};

const applyBody = (node, options) => {
    if (options.html) {
        node.innerHTML = options.html;
        return;
    }

    node.textContent = options.text || '';
};

const getToastRegion = (position) => {
    const id = `cc-app-toast-region-${position}`;
    let region = document.getElementById(id);

    if (!region) {
        region = element('div', 'cc-app-toast-region');
        region.id = id;
        region.dataset.position = position;
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-relevant', 'additions');
        document.body.appendChild(region);
    }

    return region;
};

const closeNode = (node, delay = 150, afterClose = null) => {
    if (!node?.parentNode) return;
    node.classList.add('cc-app-alert-leaving');
    window.setTimeout(() => {
        node.remove();
        afterClose?.();
    }, delay);
};

const setButtonColor = (button, color, hoverColor = null) => {
    if (!color) return;

    button.style.borderColor = color;
    button.style.backgroundColor = color;
    button.style.color = '#fff';

    if (hoverColor) {
        button.addEventListener('mouseenter', () => {
            button.style.borderColor = hoverColor;
            button.style.backgroundColor = hoverColor;
        });
        button.addEventListener('mouseleave', () => {
            button.style.borderColor = color;
            button.style.backgroundColor = color;
        });
    }
};

const showToast = (options) => {
    injectStyles();

    const region = getToastRegion(options.position || DEFAULTS.position);
    const toast = element('div', `cc-app-toast cc-app-alert-${options.icon}`);
    toast.setAttribute('role', options.icon === 'error' || options.icon === 'warning' ? 'alert' : 'status');

    const icon = element('div', 'cc-app-alert-icon');
    icon.innerHTML = ICONS[options.icon];

    const content = element('div');
    content.appendChild(element('p', 'cc-app-alert-title', options.title || options.text || 'Notificacion'));

    if (options.text || options.html) {
        const body = element('div', 'cc-app-alert-body');
        applyBody(body, options);
        content.appendChild(body);
    }

    const close = element('button', 'cc-app-alert-close');
    close.type = 'button';
    close.setAttribute('aria-label', 'Cerrar notificacion');
    close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16"><path d="M6 18 18 6M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
    close.addEventListener('click', () => closeNode(toast));

    toast.append(icon, content, close);

    const timer = Number(options.timer || DEFAULTS.timer);
    if (options.timerProgressBar && timer > 0) {
        const progress = element('div', 'cc-app-toast-progress');
        progress.style.animationDuration = `${timer}ms`;
        toast.appendChild(progress);
    }

    region.appendChild(toast);
    options.didOpen?.(toast);

    if (timer > 0) {
        window.setTimeout(() => closeNode(toast, 150, () => options.didClose?.(toast)), timer);
    }

    return Promise.resolve({ isConfirmed: true, isDismissed: false, isDenied: false });
};

const showModal = (options) => {
    injectStyles();
    modalCount += 1;

    return new Promise((resolve) => {
        const backdrop = element('div', 'cc-app-modal-backdrop');
        const modal = element('div', `cc-app-modal cc-app-alert-${options.icon}`);
        const modalId = `cc-app-alert-title-${modalCount}`;

        backdrop.setAttribute('role', 'presentation');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', modalId);

        const icon = element('div', 'cc-app-alert-icon');
        icon.innerHTML = ICONS[options.icon];
        modal.appendChild(icon);

        const title = element('h2', 'cc-app-alert-title', options.title || 'Confirmar accion');
        title.id = modalId;
        modal.appendChild(title);

        if (options.text || options.html) {
            const body = element('div', 'cc-app-alert-body');
            applyBody(body, options);
            modal.appendChild(body);
        }

        const actions = element('div', 'cc-app-alert-actions');
        const shouldShowCancel = Boolean(options.showCancelButton);
        const shouldShowConfirm = options.showConfirmButton !== false;
        const shouldShowDeny = Boolean(options.showDenyButton);
        let settled = false;

        const finish = (payload) => {
            if (settled) return;
            settled = true;
            options.willClose?.(modal);
            closeNode(backdrop, 150, () => options.didClose?.(modal));
            document.removeEventListener('keydown', onKeydown);
            resolve(payload);
        };

        const cancel = () => finish({ isConfirmed: false, isDismissed: true, isDenied: false });
        const confirm = () => finish({ isConfirmed: true, isDismissed: false, isDenied: false });
        const deny = () => finish({ isConfirmed: false, isDismissed: false, isDenied: true });

        const onKeydown = (event) => {
            if (event.key === 'Escape' && options.allowEscapeKey !== false) {
                cancel();
            }
        };

        const buttons = [];

        if (shouldShowCancel) {
            const cancelButton = element('button', 'cc-app-alert-button cc-app-alert-cancel', options.cancelButtonText);
            cancelButton.type = 'button';
            cancelButton.addEventListener('click', cancel);
            if (options.cancelButtonColor) {
                cancelButton.style.borderColor = options.cancelButtonColor;
                cancelButton.style.color = options.cancelButtonColor;
            }
            buttons.push(cancelButton);
        }

        if (shouldShowDeny) {
            const denyButton = element('button', 'cc-app-alert-button cc-app-alert-deny', options.denyButtonText || DEFAULTS.denyButtonText);
            denyButton.type = 'button';
            denyButton.addEventListener('click', deny);
            setButtonColor(denyButton, options.denyButtonColor, options.denyButtonHoverColor);
            buttons.push(denyButton);
        }

        if (shouldShowConfirm) {
            const confirmButton = element('button', 'cc-app-alert-button cc-app-alert-confirm', options.confirmButtonText);
            confirmButton.type = 'button';
            confirmButton.addEventListener('click', confirm);
            setButtonColor(confirmButton, options.confirmButtonColor, options.confirmButtonHoverColor);
            buttons.push(confirmButton);
        }

        (options.reverseButtons ? buttons.reverse() : buttons).forEach((button) => actions.appendChild(button));

        modal.appendChild(actions);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop && options.allowOutsideClick !== false) {
                cancel();
            }
        });
        document.addEventListener('keydown', onKeydown);
        options.didOpen?.(modal);

        const focusTarget = modal.querySelector('.cc-app-alert-confirm, .cc-app-alert-cancel');
        focusTarget?.focus();
    });
};

const shouldUseToast = (options) => {
    if (options.toast) return true;
    if (options.showCancelButton) return false;
    if (options.showConfirmButton === false) return true;
    return Boolean(options.timer);
};

const fireWithDefaults = (defaults, args) => {
    if (!isBrowser()) {
        return Promise.resolve({ isConfirmed: false, isDismissed: true, isDenied: false });
    }

    const options = mergeOptions(defaults, parseArgs(args));
    return shouldUseToast(options) ? showToast(options) : showModal(options);
};

const AppAlert = {
    fire: (...args) => fireWithDefaults({}, args),
    mixin: (defaults = {}) => ({
        fire: (...args) => fireWithDefaults(defaults, args),
    }),
    close: () => {
        if (!isBrowser()) return;
        document.querySelectorAll('.cc-app-toast, .cc-app-modal-backdrop').forEach((node) => closeNode(node));
    },
};

if (isBrowser()) {
    window.AppAlert = AppAlert;
}

export default AppAlert;
