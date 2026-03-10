import axios from 'axios';
import Swal from 'sweetalert2';
window.axios = axios;
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// --- Tu código de CSRF (Está perfecto) ---
const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// --------------------------------------------------------------------------
// --- INTERCEPTOR GLOBAL DE ERRORES (419, 403, 500, ETC.) ---
// --------------------------------------------------------------------------
window.axios.interceptors.response.use(
    (response) => response, // Pasa las respuestas correctas
    async (error) => {
        const originalRequest = error.config;

        if (error.response) {
            const status = error.response.status;

            // 1. Manejo del Error 419 (Expiración de Sesión / CSRF)
            if (status === 419 && !originalRequest._retry) {
                originalRequest._retry = true;
                console.warn('Error 419 detectado. Refrescando token CSRF silenciosamente...');
                
                try {
                    const response = await axios.get('/csrf-token');
                    const newCsrfToken = response.data.csrf_token;
                    
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newCsrfToken;
                    
                    const metaToken = document.querySelector('meta[name="csrf-token"]');
                    if (metaToken) {
                        metaToken.setAttribute('content', newCsrfToken);
                    }
                    
                    originalRequest.headers['X-CSRF-TOKEN'] = newCsrfToken;
                    return axios(originalRequest);
                    
                } catch (refreshError) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesión expirada',
                        text: 'Tu sesión ha expirado por inactividad. La página se recargará para continuar.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.reload();
                    });
                    return Promise.reject(refreshError);
                }
            }

            // 2. Manejo de Errores Comunes (500, 403, 404)
            // Evitamos mostrar la alerta en peticiones que el desarrollador decida silenciar
            if (!originalRequest.silent) {
                if (status >= 500) {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 5000, timerProgressBar: true,
                        icon: 'error',
                        title: 'Error del Servidor',
                        text: 'Ocurrió un problema procesando tu solicitud. El equipo técnico ha sido notificado.'
                    });
                } else if (status === 403) {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 5000, timerProgressBar: true,
                        icon: 'warning',
                        title: 'Acceso Denegado',
                        text: error.response.data?.message || 'No tienes permisos para realizar esta acción.'
                    });
                } else if (status === 404) {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
                        icon: 'info',
                        title: 'No encontrado',
                        text: 'El recurso que buscas ya no existe o fue movido.'
                    });
                }
            }
        } else if (error.request) {
            // El servidor no respondió (Caída de red)
            Swal.fire({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 5000, timerProgressBar: true,
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo contactar con el servidor. Revisa tu conexión a internet.'
            });
        }

        return Promise.reject(error);
    }
);