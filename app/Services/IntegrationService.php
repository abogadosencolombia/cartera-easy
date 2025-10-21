<?php

namespace App\Services;

use App\Models\IntegracionExternaLog;
use App\Models\IntegracionToken;
use App\Models\User; // <-- AÑADIDO: Para buscar a los administradores
use App\Notifications\IntegracionFallidaNotification; // <-- AÑADIDO: Nuestra nueva notificación
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification; // <-- AÑADIDO: El sistema para enviar notificaciones
use Illuminate\Support\Str;
use Throwable;

class IntegrationService
{
    private const REQUEST_LIMIT_PER_MINUTE = 60;
    private const FAILURE_THRESHOLD = 5; // <-- AÑADIDO: Se enviará una alerta después de 5 fallos seguidos.

    public function ejecutar(string $servicio, string $metodo, string $url, array $datos = [])
    {
        if ($this->hasExceededRequestLimit($servicio)) {
            $errorMessage = "Límite de peticiones excedido para el servicio: {$servicio}.";
            Log::warning($errorMessage);
            $errorData = ['error' => true, 'mensaje' => 'Límite de peticiones excedido.', 'status_code' => 429, 'respuesta_bruta' => $errorMessage];
            
            // También contamos el límite excedido como un fallo para las alertas.
            $this->handleConsecutiveFailures($servicio, $errorData);

            return $errorData;
        }

        // ===== ¡LA CORRECCIÓN ESTÁ AQUÍ! =====
        // Rellenamos el create() con los datos correctos.
        $log = IntegracionExternaLog::create([
            'servicio' => $servicio,
            'endpoint' => $url,
            'datos_enviados' => json_encode($datos),
            'user_id' => auth()->id(),
            'fecha_solicitud' => now(),
        ]);

        try {
            $headers = $this->getAuthHeadersForService($servicio);
            $response = Http::withHeaders($headers)->{$metodo}($url, $datos);

            if ($response->failed()) {
                $errorData = ['error' => true, 'mensaje' => 'El servicio externo respondió con un error.', 'status_code' => $response->status(), 'respuesta_bruta' => $response->body()];
                $this->handleFailedResponse($log, $response);
                $this->handleConsecutiveFailures($servicio, $errorData); // <-- AÑADIDO: Contamos el fallo
                return $errorData;
            }

            $this->resetFailureCount($servicio); // <-- AÑADIDO: Si hay éxito, reseteamos el contador de fallos.
            $log->update(['respuesta' => $response->body()]);
            return $response->json();

        } catch (Throwable $e) {
            $errorData = ['error' => true, 'mensaje' => 'La llamada HTTP falló.', 'diagnostico_tecnico' => $e->getMessage()];
            $this->handleConnectionError($log, $e);
            $this->handleConsecutiveFailures($servicio, $errorData); // <-- AÑADIDO: Contamos el fallo
            return $errorData;
        }
    }

    /**
     * NUEVO MÉTODO
     * Lleva la cuenta de los fallos consecutivos y envía una alerta si se supera el umbral.
     */
    private function handleConsecutiveFailures(string $service, array $errorDetails): void
    {
        $key = 'integration_failures_' . Str::slug($service);
        
        // Incrementamos el contador de fallos en la caché.
        $failures = Cache::increment($key);

        if ($failures >= self::FAILURE_THRESHOLD) {
            // Si superamos el umbral, buscamos a los admins y les enviamos la notificación.
            $admins = User::where('tipo_usuario', 'admin')->get();
            Notification::send($admins, new IntegracionFallidaNotification($service, $errorDetails));
            
            // Reseteamos el contador para no enviar alertas en cada fallo siguiente.
            Cache::forget($key);
        }
    }

    /**
     * NUEVO MÉTODO
     * Resetea el contador de fallos de un servicio tras una llamada exitosa.
     */
    private function resetFailureCount(string $service): void
    {
        $key = 'integration_failures_' . Str::slug($service);
        Cache::forget($key);
    }

    // --- El resto de los métodos de ayuda (hasExceededRequestLimit, etc.) siguen igual ---
    
    private function hasExceededRequestLimit(string $service): bool
    {
        $key = 'integration_requests_' . Str::slug($service);
        $requests = Cache::get($key, []);
        $requestsInLastMinute = array_filter($requests, fn($timestamp) => $timestamp > (time() - 60));
        if (count($requestsInLastMinute) >= self::REQUEST_LIMIT_PER_MINUTE) {
            return true;
        }
        $requestsInLastMinute[] = time();
        Cache::put($key, $requestsInLastMinute, 60);
        return false;
    }

    private function getAuthHeadersForService(string $servicio): array
    {
        $tokenData = IntegracionToken::where('proveedor', $servicio)->where('activo', true)->first();
        if (!$tokenData) {
            Log::warning("No se encontraron credenciales activas para el servicio: {$servicio}");
            return [];
        }
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($tokenData->api_key) {
            $headers['Authorization'] = 'Bearer ' . $tokenData->api_key;
        }
        return $headers;
    }

    private function handleFailedResponse(IntegracionExternaLog $log, Response $response): void
    {
        $log->update(['respuesta' => json_encode(['error' => 'Respuesta de error del servidor', 'status_code' => $response->status(), 'body' => $response->body()])]);
        Log::warning("Respuesta de error desde {$log->servicio}", ['status' => $response->status()]);
    }

    private function handleConnectionError(IntegracionExternaLog $log, Throwable $e): void
    {
        $log->update(['respuesta' => json_encode(['error' => 'Fallo en la conexión', 'mensaje_tecnico' => $e->getMessage()])]);
        Log::error("Fallo de conexión en IntegrationService para {$log->servicio}", ['error' => $e->getMessage()]);
    }

    /**
     * Simula la validación de una cooperativa, conteniendo la lógica
     * que antes estaba en el archivo de rutas.
     *
     * @param string $nit
     * @return array
     */
    public function simularValidacionSupersolidaria(string $nit): array
    {
        $cooperativasSimuladas = [
            '900123456-7' => ['nombre' => 'Cooperativa El Futuro', 'estado' => 'Activa', 'fecha_registro' => '2010-05-20'],
            '800987654-3' => ['nombre' => 'CoopProgreso', 'estado' => 'En Liquidación', 'fecha_registro' => '2005-11-15'],
            '123456789-0' => ['nombre' => 'Cooperativa Fantasma', 'estado' => 'Cancelada', 'fecha_registro' => '2001-01-10'],
            '364'=> ['nombre' => 'Cooperativa Nueva', 'estado' => 'Activa', 'fecha_registro' => '2025-07-15'],
            '123456'=> ['nombre' => 'Cooperativa Pendiente', 'estado' => 'Activa', 'fecha_registro' => '2025-07-11'],
        ];

        if (array_key_exists($nit, $cooperativasSimuladas)) {
            return $cooperativasSimuladas[$nit];
        }

        return [
            'error' => true,
            'mensaje' => 'Cooperativa no encontrada en los registros de Supersolidaria.',
            'status_code' => 404
        ];
    }
}
