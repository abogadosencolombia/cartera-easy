import Swal from 'sweetalert2';

export function useNotifications() {
    // Configuración base para Toasts
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        showClass: {
            popup: 'animate__animated animate__fadeInRight'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutRight'
        },
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    const notify = (type, message, title = '') => {
        Toast.fire({
            icon: type, // success, error, warning, info, question
            title: title || message,
            text: title ? message : ''
        });
    };

    const confirm = async (options = {}) => {
        return await Swal.fire({
            title: options.title || '¿Estás seguro?',
            text: options.text || 'Esta acción no se puede deshacer.',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5', // Indigo 600
            cancelButtonColor: '#ef4444',  // Red 500
            confirmButtonText: options.confirmText || 'Sí, confirmar',
            cancelButtonText: options.cancelText || 'Cancelar',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-xl border dark:border-gray-700 dark:bg-gray-800 dark:text-white',
                title: 'text-lg font-bold',
                htmlContainer: 'text-sm opacity-80'
            }
        });
    };

    return { notify, confirm };
}
