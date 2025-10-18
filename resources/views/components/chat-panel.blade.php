@props(['user'])

<div
    x-data="chatPanel({
        userId: {{ $user->id }},
        userName: '{{ $user->name }}',
    })"
    class="fixed bottom-0 right-0 z-50 p-4"
>
    <button @click="toggle" class="bg-blue-600 text-white rounded-full w-16 h-16 flex items-center justify-center shadow-lg hover:bg-blue-700 transition-transform transform hover:scale-110">
        <svg x-show="!isOpen" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
        <svg x-show="isOpen" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>

    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-4"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-4"
        class="absolute bottom-24 right-0 w-96 bg-white rounded-lg shadow-2xl border border-gray-200 flex flex-col"
        style="height: 600px; display: none;"
    >
        <div class="bg-blue-600 text-white p-4 rounded-t-lg flex items-center">
            <div class="w-2.5 h-2.5 bg-green-400 rounded-full mr-2"></div>
            <h3 class="text-lg font-semibold">Asistente Virtual</h3>
        </div>

        <div x-ref="messages" class="flex-1 p-4 overflow-y-auto space-y-4">
            <div class="flex">
                <div class="bg-gray-200 text-gray-800 p-3 rounded-lg max-w-xs">
                    <p>Hola {{ $user->name }}, ¿en qué puedo ayudarte hoy?</p>
                </div>
            </div>
        </div>

        <div class="p-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
            <div x-show="fileName" class="bg-gray-200 p-2 rounded-md mb-2 flex justify-between items-center text-sm" style="display: none;">
                <span class="truncate" x-text="fileName"></span>
                <button @click="removeFile" class="text-gray-500 hover:text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <form @submit.prevent="sendMessage" class="flex items-center space-x-2">
                <input type="file" x-ref="fileInput" @change="handleFileSelect" class="hidden">
                <button @click.prevent="selectFile" type="button" class="p-2 text-gray-500 hover:text-blue-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                </button>
                <input
                    x-model="newMessage"
                    type="text"
                    placeholder="Escribe tu mensaje..."
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :disabled="isLoading"
                >
                <button type="submit" class="bg-blue-600 text-white rounded-full w-12 h-12 flex items-center justify-center hover:bg-blue-700 transition shrink-0" :disabled="isLoading">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                </button>
            </form>
        </div>
    </div>
</div>