<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue';
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
const page = usePage();
const user = computed(() => page.props.auth.user);

// --- FUNCIONES ---
const toggleChat = () => {
  isOpen.value = !isOpen.value;
};

const scrollToBottom = async () => {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
};

const addMessage = (html, type) => {
  const timestamp = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  messages.value.push({ id: Date.now() + Math.random(), html, type, timestamp });
  scrollToBottom();
};

// --- PARSER SIMPLE DE MARKDOWN A HTML ---
const formatBotResponse = (text) => {
    if (!text) return '';
    
    // 1. Sanitizar HTML básico (Evitar inyección de código malicioso si el bot repitiera algo raro)
    let formatted = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

    // 2. Negrillas: **texto** -> <strong>texto</strong>
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
    
    // 3. Negrillas alternativa: __texto__ -> <strong>texto</strong>
    formatted = formatted.replace(/__(.*?)__/g, '<strong class="font-bold text-slate-900">$1</strong>');

    // 4. Cursivas: *texto* -> <em>texto</em>
    formatted = formatted.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');

    // 5. Listas numéricas: "1. Texto" -> Salto de línea + Negrita en el número
    // Esto ayuda a que "1. Paso uno 2. Paso dos" se vea separado visualmente
    formatted = formatted.replace(/(\d+\.)\s/g, '<br><strong class="text-slate-700">$1</strong> ');

    // 6. Listas con viñetas: "- Texto" -> Bullet point real
    formatted = formatted.replace(/^\-\s/gm, '• ');

    // 7. Convertir saltos de línea explícitos (\n) en etiquetas <br>
    formatted = formatted.replace(/\n/g, '<br>');

    // Limpiar saltos de línea dobles al inicio si se generaron por el reemplazo de listas
    if (formatted.startsWith('<br>')) {
        formatted = formatted.substring(4);
    }

    return formatted;
};

const sendMessage = async () => {
  const messageText = newMessage.value.trim();
  if (messageText === '' || !user.value) return;

  isLoading.value = true;
  
  // Sanitizar mensaje del usuario antes de mostrarlo (Seguridad XSS)
  const safeUserMessage = messageText.replace(/</g, "&lt;").replace(/>/g, "&gt;");
  addMessage(safeUserMessage, 'user');
  
  newMessage.value = '';

  try {
    const response = await axios.post('/chatbot/send', {
      message: messageText,
    });
    
    console.log('[Chat] Mensaje enviado exitosamente:', response.data);
    
  } catch (error) {
    console.error('[Chat] Error enviando mensaje:', error);
    addMessage('Hubo un error al conectar con el servidor. Por favor, inténtalo de nuevo.', 'error');
    isLoading.value = false;
  }
};

// --- CICLO DE VIDA Y WATCHERS ---
onMounted(() => {
  console.log('[Chat] Componente montado');
  
  if (user.value && window.Echo) {
    const userId = user.value.id;
    const channelName = `App.Models.User.${userId}`;
    
    console.log(`[Chat] Intentando suscribirse al canal: ${channelName}`);
    
    const channel = window.Echo.private(channelName);

    channel
      .subscribed(() => {
        console.log(`[Chat] ✅ Suscrito correctamente a ${channelName}`);
      })
    
    .listen('.chatbot.response', (e) => {
        console.log('[Chat] 📨 Evento recibido:', e);
        
        isLoading.value = false;
        if (e && e.body) {
          // AQUI APLICAMOS EL FORMATO INTELIGENTE
          const formattedBody = formatBotResponse(e.body);
          addMessage(formattedBody, 'bot'); 
        } else {
          console.error('[Chat] Formato de evento inesperado:', e);
        }
    })
      .error((error) => {
        console.error('[Chat] ❌ Error de Echo:', error);
      });

  } else {
    if (!user.value) console.warn('[Chat] ⚠️ No hay usuario autenticado');
    if (!window.Echo) console.warn('[Chat] ⚠️ Echo no está disponible');
  }
});

watch(isOpen, (newVal) => {
  if (newVal) {
    scrollToBottom();
    setTimeout(() => {
      if (messages.value.length === 0) {
        // Mensaje inicial formateado bonito
        addMessage('¡Hola! Soy tu asistente virtual. <strong>¿En qué puedo ayudarte hoy?</strong>', 'bot');
      }
      inputMessage.value?.focus();
    }, 400); 
  }
});
</script>

<style>
/* Variables y estilos base */
:root {
  --chat-bg: #f8f9fa;
  --header-bg: #111827;
}

/* Scrollbar personalizado y elegante */
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Animación de escribiendo (...) */
.typing-indicator { display: flex; align-items: center; gap: 5px; }
.typing-indicator span { 
    height: 6px; width: 6px; 
    background-color: #94a3b8; 
    border-radius: 50%; display: inline-block; 
    animation: pulse 1.5s infinite ease-in-out; 
}
.typing-indicator span:nth-of-type(1) { animation-delay: 0s; }
.typing-indicator span:nth-of-type(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-of-type(3) { animation-delay: 0.4s; }
@keyframes pulse { 
  0%, 60%, 100% { opacity: 0.3; transform: scale(0.8); } 
  30% { opacity: 1; transform: scale(1.1); }
}

/* Transiciones suaves para la ventana */
.chat-window-enter-active, .chat-window-leave-active { 
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  transform-origin: bottom right;
}
.chat-window-enter-from, .chat-window-leave-to { 
  opacity: 0; 
  transform: scale(0.95) translateY(20px); 
}

/* Transiciones para el botón flotante */
.fab-icon-enter-active, .fab-icon-leave-active { 
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s; 
}
.fab-icon-enter-from { opacity: 0; transform: rotate(-90deg) scale(0.5); }
.fab-icon-leave-to { opacity: 0; transform: rotate(90deg) scale(0.5); }

/* Animación de entrada de mensajes */
.message-list-enter-active { transition: all 0.4s ease-out; }
.message-list-enter-from { opacity: 0; transform: translateY(10px) scale(0.95); }
</style>

<template>
  <div v-if="user" class="fixed bottom-6 right-6 z-[9999]">
    <transition name="chat-window">
      <div v-if="isOpen" class="w-[90vw] sm:w-[400px] bg-white rounded-2xl shadow-2xl flex flex-col h-[80vh] max-h-[650px] overflow-hidden border border-slate-200 ring-1 ring-slate-900/5 font-sans">
        
        <!-- Header -->
        <div class="bg-slate-900 text-white p-4 flex-shrink-0 flex justify-between items-center shadow-md z-10">
          <div class="flex items-center gap-3">
            <div class="relative">
              <div class="w-10 h-10 bg-white/10 backdrop-blur-sm rounded-full flex items-center justify-center border border-white/20">
                 <!-- Icono Robot mejorado -->
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                 </svg>
              </div>
              <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-slate-900 rounded-full animate-pulse"></span>
            </div>
            <div>
              <h3 class="font-bold text-base tracking-wide">Asistente Virtual</h3>
              <p class="text-xs text-slate-300 font-medium">Abogados Colombia</p>
            </div>
          </div>
          <button @click="toggleChat" class="text-slate-400 hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
        
        <!-- Messages Area -->
        <div ref="messagesContainer" class="flex-1 p-4 overflow-y-auto bg-slate-50 custom-scrollbar scroll-smooth">
            <transition-group name="message-list" tag="div" class="space-y-6">
                <!-- Mensaje de bienvenida (si no hay mensajes) -->
                <div v-if="messages.length === 0 && !isLoading" class="text-center py-8 opacity-60">
                    <p class="text-sm text-slate-500">Hazme una pregunta sobre tus casos.</p>
                </div>

                <div v-for="message in messages" :key="message.id" 
                     :class="message.type === 'user' ? 'flex justify-end' : 'flex justify-start'">
                  <div class="flex flex-col max-w-[85%]">
                    <!-- Burbuja de mensaje -->
                    <div 
                      class="px-5 py-3.5 rounded-2xl text-[15px] leading-relaxed shadow-sm" 
                      :class="{
                        'bg-blue-600 text-white rounded-br-none': message.type === 'user',
                        'bg-white text-slate-700 rounded-bl-none border border-slate-200': message.type === 'bot',
                        'bg-red-50 border border-red-200 text-red-700': message.type === 'error',
                      }" 
                      v-html="message.html">
                    </div>
                    <!-- Timestamp -->
                    <span class="text-[10px] text-slate-400 mt-1.5 px-1 font-medium tracking-wide" 
                          :class="message.type === 'user' ? 'text-right' : 'text-left'">
                      {{ message.timestamp }}
                    </span>
                  </div>
                </div>
            </transition-group>
            
          <!-- Indicador de carga -->
          <div v-if="isLoading" class="flex justify-start pt-2">
            <div class="px-4 py-3 rounded-2xl rounded-bl-none bg-white shadow-sm border border-slate-200 flex items-center gap-3">
              <span class="text-xs text-slate-400 font-medium">Escribiendo</span>
              <div class="typing-indicator"><span></span><span></span><span></span></div>
            </div>
          </div>
        </div>
        
        <!-- Input Area -->
        <div class="p-3 bg-white border-t border-slate-100">
          <form @submit.prevent="sendMessage" class="flex items-center gap-2 p-1 relative">
            <input 
                ref="inputMessage" 
                type="text" 
                v-model="newMessage" 
                :disabled="isLoading" 
                placeholder="Escribe tu consulta aquí..." 
                class="flex-1 w-full text-sm bg-slate-100 text-slate-800 border-none rounded-xl py-3.5 px-5 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition-all duration-300 placeholder-slate-400 font-medium"
            >
            
            <button 
                type="submit" 
                :disabled="isLoading || newMessage.trim() === ''" 
                class="bg-blue-600 text-white rounded-xl h-12 w-12 flex items-center justify-center transition-all duration-200 shadow-md shadow-blue-600/20" 
                :class="{'hover:bg-blue-700 hover:scale-105 active:scale-95': newMessage.trim() !== '', 'bg-slate-300 cursor-not-allowed shadow-none': newMessage.trim() === ''}"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-0" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.428A1 1 0 009.586 16.5l-4.293-4.293a1 1 0 010-1.414l7-7a1 1 0 011.414 0l4.293 4.293-5.586 5.586a1 1 0 00.28 1.55l5 1.428a1 1 0 001.17-1.408l-7-14z" />
              </svg>
            </button>
          </form>
        </div>
      </div>
    </transition>

    <!-- Botón Flotante -->
    <button @click="toggleChat" class="group bg-blue-600 text-white rounded-full h-14 w-14 flex items-center justify-center shadow-[0_8px_30px_rgb(0,0,0,0.2)] hover:bg-blue-700 transition-all duration-300 transform hover:scale-105 active:scale-95 hover:shadow-blue-600/40">
      <transition name="fab-icon" mode="out-in">
        <svg v-if="!isOpen" key="open" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
        <svg v-else key="close" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </transition>
      
      <!-- Notificación roja si está cerrado y llega mensaje (opcional, lógica futura) -->
      <!-- <span class="absolute top-0 right-0 block h-3 w-3 rounded-full ring-2 ring-white bg-red-500"></span> -->
    </button>
  </div>
</template>