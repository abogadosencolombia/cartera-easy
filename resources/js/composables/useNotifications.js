import AppAlert from '@/Utils/appAlert';

export function useNotifications() {
    const Toast = AppAlert.mixin({
        toast: true,
        timer: 4000,
    });

    const notify = (type, message, title = '') => {
        Toast.fire({
            icon: type, // success, error, warning, info, question
            title: title || message,
            text: title ? message : ''
        });
    };

    const confirm = async (options = {}) => {
        return await AppAlert.fire({
            title: options.title || '¿Estás seguro?',
            text: options.text || 'Esta acción no se puede deshacer.',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Sí, confirmar',
            cancelButtonText: options.cancelText || 'Cancelar',
            reverseButtons: true,
        });
    };

    return { notify, confirm };
}
