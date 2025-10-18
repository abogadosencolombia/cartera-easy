<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue';
// Se importa 'usePage' de Inertia para acceder a los datos compartidos del servidor, como el usuario autenticado.
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

// --- GESTIÓN DE ESTADO ---
const isOpen = ref(false);
const isLoading = ref(false);
const newMessage = ref('');
const messages = ref([]);

// --- REFERENCIAS DEL DOM ---
const messagesContainer = ref(null);
const inputMessage = ref(null);

// --- DATOS DE USUARIO DINÁMICOS ---
// Se obtiene el objeto 'page' que contiene toda la información que Laravel comparte con el frontend.
const page = usePage();
// Se crea una propiedad computada 'user' que apunta directamente a los datos del usuario autenticado.
// Esto asegura que siempre tengamos la información más actualizada del usuario que ha iniciado sesión.
const user = computed(() => page.props.auth.user);

// --- FUNCIONES ---

/**
 * Alterna la visibilidad de la ventana de chat.
 */
const toggleChat = () => {
  isOpen.value = !isOpen.value;
};

/**
 * Desplaza el contenedor de mensajes hasta el final para mostrar siempre el último mensaje.
 */
const scrollToBottom = async () => {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
};

/**
 * Agrega un nuevo mensaje al array 'messages', lo que lo renderiza en la UI.
 * @param {string} html - El contenido del mensaje.
 * @param {'user'|'bot'|'error'} type - Define el estilo y la alineación de la burbuja del mensaje.
 */
const addMessage = (html, type) => {
  const timestamp = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  messages.value.push({ id: Date.now() + Math.random(), html, type, timestamp });
  scrollToBottom();
};

/**
 * Envía el mensaje del usuario al webhook de n8n.
 */
const sendMessage = async () => {
  const messageText = newMessage.value.trim();
  // Se asegura de que no se envíen mensajes vacíos y de que haya un usuario autenticado.
  if (messageText === '' || !user.value) return;

  isLoading.value = true;
  addMessage(messageText, 'user');
  newMessage.value = '';

  try {
    const formData = new FormData();
    formData.append('message', messageText);
    // ¡CRÍTICO! Se envía el ID del usuario real y dinámico.
    formData.append('userId', user.value.id);

    // URL del webhook en n8n que inicia el flujo de la IA.
    const n8nWebhookUrl = 'https://cartera-crearcop-n8n.hrymiz.easypanel.host/webhook/messages-customers';

    await axios.post(n8nWebhookUrl, formData);
    
    // El indicador 'isLoading' se desactivará cuando el evento de Echo sea recibido.

  } catch (error) {
    console.error('Error enviando el mensaje a n8n:', error);
    addMessage('Hubo un error al conectar con el servidor. Por favor, inténtalo de nuevo.', 'error');
    isLoading.value = false;
  }
};


// --- CICLO DE VIDA Y WATCHERS ---

onMounted(() => {
  // Se ejecuta cuando el componente está listo en el navegador.
  console.log('[Chat Debug] Componente montado.');
  
  // Solo intenta conectarse a Echo si hay un usuario y la librería Echo existe.
  if (user.value && window.Echo) {
    console.log(`[Chat Debug] Usuario autenticado (ID: ${user.value.id}). Intentando conectar a Echo.`);
    
    // ¡CRÍTICO! Se suscribe a un canal privado y único para el usuario actual.
    const channel = window.Echo.private(`chat.${user.value.id}`);

    channel
      .subscribed(() => {
        // Si ves este mensaje, el frontend se conectó correctamente al canal.
        console.log(`[Chat Debug] ¡ÉXITO! Suscrito correctamente al canal privado: chat.${user.value.id}`);
      })
      .listen('.chatbot.response', (e) => {
        // Si ves este mensaje, ¡todo el sistema funciona!
        console.log('[Chat Debug] ¡EVENTO RECIBIDO!', e);
        
        isLoading.value = false; // Detiene el indicador de "escribiendo".
        // Añade el mensaje del bot a la UI.
        if (e && e.message) {
            addMessage(e.message, 'bot');
        } else {
            console.error('[Chat Debug] Evento de chat recibido con formato inesperado:', e);
        }
      })
      .error((error) => {
        // Si ves este error, el problema está en la autenticación del canal privado.
        console.error('[Chat Debug] ERROR de autenticación de Echo. Revisa la configuración de broadcasting.php y Reverb/Pusher.', error);
      });

  } else {
      if (!user.value) {
          console.warn('[Chat Debug] No hay un usuario autenticado. Echo no se iniciará.');
      }
      if (!window.Echo) {
          console.warn('[Chat Debug] La librería Laravel Echo (window.Echo) no está disponible. Revisa tu archivo resources/js/bootstrap.js.');
      }
  }
});

// Se activa cada vez que se abre la ventana del chat.
watch(isOpen, (newVal) => {
  if (newVal) {
    scrollToBottom();
    setTimeout(() => {
      // Muestra el mensaje de bienvenida solo la primera vez.
      if (messages.value.length === 0) {
        addMessage('¡Hola! Soy tu asistente virtual. ¿En qué puedo ayudarte hoy?', 'bot');
      }
      // Pone el cursor automáticamente en el campo de texto.
      inputMessage.value?.focus();
    }, 400); 
  }
});
</script>

<style>
/* --- ESTILOS "OBRA DE ARTE" --- */
:root {
  --chat-bg: #f8f9fa;
  --header-bg: #111827;
  --user-bubble-bg: linear-gradient(135deg, #007BFF, #0056b3);
  --bot-bubble-bg: #E9ECEF;
  --text-dark: #212529;
  --text-light: #ffffff;
  --text-muted: #6c757d;
  --accent-color: #007BFF;
}

.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #ced4da; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #adb5bd; }

/* Indicador de escribiendo con animación de pulso */
.typing-indicator { display: flex; align-items: center; gap: 5px; }
.typing-indicator span { 
    height: 9px; width: 9px; 
    background-color: var(--text-muted); 
    border-radius: 50%; display: inline-block; 
    animation: pulse 1.5s infinite ease-in-out; 
}
.typing-indicator span:nth-of-type(1) { animation-delay: 0s; }
.typing-indicator span:nth-of-type(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-of-type(3) { animation-delay: 0.4s; }
@keyframes pulse { 
  0%, 60%, 100% { opacity: 0.3; transform: scale(0.8); } 
  30% { opacity: 1; transform: scale(1); }
}

/* --- ANIMACIONES DE TRANSICIÓN --- */

/* Ventana de Chat: emerge desde el botón */
.chat-window-enter-active, .chat-window-leave-active { 
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  transform-origin: bottom right;
}
.chat-window-enter-from, .chat-window-leave-to { 
  opacity: 0; 
  transform: scale(0.1); 
}

/* Botón flotante: rotación y cambio de ícono */
.fab-icon-enter-active, .fab-icon-leave-active { 
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s; 
}
.fab-icon-enter-from { opacity: 0; transform: rotate(-180deg) scale(0.5); }
.fab-icon-leave-to { opacity: 0; transform: rotate(180deg) scale(0.5); }

/* Mensajes: aparecen con elegancia */
.message-list-enter-active {
    transition: all 0.4s ease-out;
}
.message-list-enter-from {
    opacity: 0;
    transform: translateY(20px);
}
.message-list-leave-active {
    transition: all 0.3s ease-in;
    position: absolute; /* Evita que salten al eliminarse */
}
.message-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>

<template>
  <div v-if="user" class="fixed bottom-5 right-5 z-[1000]">
    <!-- Ventana del Chat -->
    <transition name="chat-window">
      <div v-if="isOpen" class="w-[calc(100vw-40px)] sm:w-96 bg-white rounded-xl shadow-2xl flex flex-col h-[70vh] max-h-[600px] overflow-hidden border border-slate-200/80">
        
        <!-- Cabecera Minimalista -->
        <div class="bg-white text-slate-800 p-4 flex-shrink-0 border-b border-slate-200/80 flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="relative">
              <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"> <path stroke="none" d="M0 0h24v24H0z" fill="none"/> <path d="M4 12v-3a3 3 0 0 1 3 -3h10a3 3 0 0 1 3 3v6a3 3 0 0 1 -3 3h-7" /> <path d="M10 8h4" /> <path d="M10 12h2" /> <path d="M3 18h4" /> <path d="M5 16v4" /> </svg>
              </div>
              <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border border-white rounded-full"></span>
            </div>
            <div>
              <h3 class="font-semibold text-base">Asistente Virtual</h3>
              <p class="text-xs text-slate-500">En línea</p>
            </div>
          </div>
        </div>
        
        <!-- Contenedor de Mensajes -->
        <div ref="messagesContainer" class="flex-1 p-4 overflow-y-auto bg-slate-100/50 custom-scrollbar">
            <transition-group name="message-list" tag="div" class="space-y-4">
                <div v-for="message in messages" :key="message.id" 
                     :class="message.type === 'user' ? 'flex justify-end' : 'flex justify-start'">
                  <div class="flex flex-col max-w-[85%]">
                    <div 
                      class="px-4 py-2.5 rounded-2xl break-words text-sm font-medium" 
                      :class="{
                        'bg-slate-800 text-white rounded-br-lg shadow-lg': message.type === 'user',
                        'bg-white text-slate-800 rounded-bl-lg shadow-sm border border-slate-200/60': message.type === 'bot',
                        'bg-red-50 border border-red-200/80 text-red-700 rounded-lg': message.type === 'error',
                      }" 
                      v-html="message.html">
                    </div>
                    <span class="text-xs text-slate-400 mt-1.5 px-1" :class="message.type === 'user' ? 'text-right' : 'text-left'">
                      {{ message.timestamp }}
                    </span>
                  </div>
                </div>
            </transition-group>
            
          <!-- Indicador de "Escribiendo..." -->
          <div v-if="isLoading" class="flex justify-start pt-4">
            <div class="px-4 py-3 rounded-2xl rounded-bl-lg bg-white shadow-sm border border-slate-200/60">
              <div class="typing-indicator"><span></span><span></span><span></span></div>
            </div>
          </div>
        </div>
        
        <!-- Pie de página (Input de mensaje) -->
        <div class="p-2 bg-white/70 backdrop-blur-sm border-t border-slate-200/60 flex-shrink-0">
          <form @submit.prevent="sendMessage" class="flex items-center gap-2 p-1">
            <input ref="inputMessage" type="text" v-model="newMessage" :disabled="isLoading" placeholder="Escribe un mensaje..." class="flex-1 w-full text-sm bg-slate-100 border-transparent rounded-full py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:opacity-70 transition-all duration-300">
            
            <!-- Botón de envío con animación -->
            <button type="submit" :disabled="isLoading || newMessage.trim() === ''" class="bg-slate-800 text-white rounded-full h-10 w-10 flex items-center justify-center flex-shrink-0 transition-all duration-300 transform focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-800" :class="{'scale-110 hover:bg-slate-900': newMessage.trim() !== '', 'scale-100 bg-slate-400 cursor-not-allowed': newMessage.trim() === ''}">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.428A1 1 0 009.586 16.5l-4.293-4.293a1 1 0 010-1.414l7-7a1 1 0 011.414 0l4.293 4.293-5.586 5.586a1 1 0 00.28 1.55l5 1.428a1 1 0 001.17-1.408l-7-14z" />
              </svg>
            </button>
          </form>
        </div>
      </div>
    </transition>

    <!-- Botón Flotante Animado -->
    <button @click="toggleChat" class="bg-slate-800 text-white rounded-full h-16 w-16 flex items-center justify-center shadow-xl hover:bg-slate-900 transition-all duration-300 transform hover:scale-110 focus:outline-none focus:ring-4 focus:ring-slate-400/50">
      <transition name="fab-icon" mode="out-in">
        <!-- Icono de Mensaje -->
        <svg v-if="!isOpen" key="open" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
        <!-- Icono de Cerrar (X) -->
        <svg v-else key="close" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </transition>
    </button>
  </div>
</template>

