import axios from 'axios';
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

// --- Tu código de Laravel Echo (Está perfecto) ---
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
        },
    },
});


// --------------------------------------------------------------------------
// --- INTERCEPTOR AVANZADO "ANTI-419" (SIN RECARGA) ---
// --------------------------------------------------------------------------
// Este es el "vigilante inteligente" que fulmina el error 419
// sin que pierdas los datos de tus formularios.
window.axios.interceptors.response.use(
    (response) => response, // Pasa las respuestas correctas
    async (error) => {
        const originalRequest = error.config;

        // Comprobamos si es un error 419 y si no es un reintento.
        // (error.response) es importante para no fallar en errores de red
        if (error.response && error.response.status === 419 && !originalRequest._retry) {
            
            originalRequest._retry = true; // Marcamos como reintentado
            
            console.warn('Error 419 detectado. Refrescando token CSRF silenciosamente...');
            
            try {
                // 1. Pedimos un token nuevo a nuestra ruta de Laravel
                const response = await axios.get('/csrf-token');
                const newCsrfToken = response.data.csrf_token;
                
                // 2. Actualizamos el token en la cabecera por defecto de Axios
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newCsrfToken;
                
                // 3. Actualizamos el token en la meta-etiqueta (buena práctica)
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                if (metaToken) {
                    metaToken.setAttribute('content', newCsrfToken);
                }
                
                // 4. Actualizamos la cabecera de la petición original
                originalRequest.headers['X-CSRF-TOKEN'] = newCsrfToken;

                // 5. ¡Reintentamos la petición original con el nuevo token!
                // El usuario ni se dará cuenta y no perderá su formulario.
                console.log('Token refrescado. Reintentando la petición original...');
                return axios(originalRequest);
                
            } catch (refreshError) {
                // Si esto falla, es un problema mayor. Ahí sí, recargamos.
                console.error('No se pudo refrescar el token CSRF. Recarga manual necesaria.', refreshError);
                alert('Tu sesión ha expirado y no se pudo refrescar. La página se recargará.');
                window.location.reload();
                return Promise.reject(refreshError);
            }
        }

        // Si no es un 419, o si ya reintentamos, dejamos que falle.
        return Promise.reject(error);
    }
);