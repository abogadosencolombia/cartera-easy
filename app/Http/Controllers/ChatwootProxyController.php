<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootProxyController extends Controller
{
    private $chatwootUrl = 'https://chatwoot.servilutioncrm.cloud';
    private $chatwootHost = 'chatwoot.servilutioncrm.cloud';

    private function rewriteBody(string $body, Request $request): string
    {
        $localBase = $request->getSchemeAndHttpHost();
        $localHost = $request->getHttpHost();
        $escapedLocalBase = str_replace('/', '\\/', $localBase);

        return str_replace(
            [
                'https://' . $this->chatwootHost,
                'http://' . $this->chatwootHost,
                '//' . $this->chatwootHost,
                'https:\\/\\/' . $this->chatwootHost,
                'http:\\/\\/' . $this->chatwootHost,
                'hostURL: \'\'',
                '"/sw.js"',
                '\'/sw.js\'',
                '"/cable"',
                '\'/cable\'',
                'ws://' . $localHost . '/cable',
                'wss://' . $localHost . '/cable',
            ],
            [
                $localBase,
                $localBase,
                '//' . $localHost,
                $escapedLocalBase,
                $escapedLocalBase,
                'hostURL: \'' . $localBase . '\'',
                '"/chatwoot-sw.js"',
                '\'/chatwoot-sw.js\'',
                '"wss://' . $this->chatwootHost . '/cable"',
                '\'wss://' . $this->chatwootHost . '/cable\'',
                'wss://' . $this->chatwootHost . '/cable',
                'wss://' . $this->chatwootHost . '/cable',
            ],
            $body
        );
    }

    public function proxy(Request $request)
    {
        // 1. Obtener la ruta solicitada (ej: /app/login)
        $uri = $request->getRequestUri();
        $proxyUri = $uri === '/chatwoot-sw.js' ? '/sw.js' : $uri;
        $fullUrl = $this->chatwootUrl . $proxyUri;
        $method = $request->method();
        
        // 2. Preparar cabeceras para el túnel
        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            // No reenviamos cabeceras de transporte/origen local
            if (in_array(strtolower($key), [
                'host',
                'content-length',
                'connection',
                'accept-encoding',
                'forwarded',
                'x-forwarded-for',
                'x-forwarded-host',
                'x-forwarded-port',
                'x-forwarded-proto',
                'x-real-ip',
            ])) {
                continue;
            }
            $headers[$key] = $value[0];
        }
        
        $localHost = $request->getHost();

        // Identidad para Chatwoot
        $headers['host'] = $this->chatwootHost;
        $headers['origin'] = $this->chatwootUrl;
        $headers['referer'] = $this->chatwootUrl . $proxyUri;
        $headers['x-forwarded-host'] = $this->chatwootHost;
        $headers['x-forwarded-proto'] = 'https';
        $headers['x-forwarded-port'] = '443';
        
        // Mapear cookies del navegador -> Chatwoot
        if (isset($headers['cookie'])) {
            $headers['cookie'] = str_replace($localHost, $this->chatwootHost, $headers['cookie']);
        }

        try {
            $pendingRequest = Http::withHeaders($headers)
                ->withOptions([
                    'allow_redirects' => false, // Nosotros controlamos el flujo
                    'verify' => false,
                ]);

            if (!in_array($method, ['GET', 'HEAD'])) {
                $pendingRequest->withBody($request->getContent(), $request->header('Content-Type') ?? 'application/json');
            }

            $response = $pendingRequest->send($method, $fullUrl);

            $status = $response->status();
            $body = $response->body();
            $contentType = $response->header('Content-Type');
            $isRedirect = $status >= 300 && $status < 400;

            // 3. REESCRITURA DE CONTENIDO (Para que Chatwoot no intente salirse del proxy)
            if (is_string($contentType) && (
                str_contains($contentType, 'text/html')
                || str_contains($contentType, 'javascript')
                || str_contains($contentType, 'json')
                || str_contains($contentType, 'text/css')
            )) {
                $body = $this->rewriteBody($body, $request);
            }

            $laravelResponse = $isRedirect ? response('', $status) : response($body, $status);
            if (!$isRedirect && is_string($contentType) && $contentType !== '') {
                $laravelResponse->header('Content-Type', $contentType);
            }

            // 4.1 Copiar headers upstream (menos los que causan conflicto local)
            foreach ($response->toPsrResponse()->getHeaders() as $name => $values) {
                $lowerName = strtolower($name);
                if (in_array($lowerName, ['content-length', 'content-encoding', 'transfer-encoding', 'connection', 'set-cookie', 'location', 'x-frame-options', 'content-security-policy'])) {
                    continue;
                }
                foreach ($values as $value) {
                    $laravelResponse->headers->set($name, $value, false);
                }
            }

            // 4. ELIMINAR BLOQUEOS DE SEGURIDAD
            $laravelResponse->headers->remove('X-Frame-Options');
            $laravelResponse->headers->remove('Content-Security-Policy');
            $laravelResponse->header('X-Frame-Options', 'ALLOWALL');
            $laravelResponse->header('Content-Security-Policy', "frame-ancestors *;");

            // 4.2 Caching: dinámico sin caché, estáticos con caché para rendimiento
            $isStaticPath = preg_match('#^/(vite|assets|brand-assets|packs|storage)/#', $proxyUri)
                || preg_match('#\.(js|css|png|jpe?g|svg|ico|woff2?|ttf|map)$#i', $proxyUri)
                || in_array($proxyUri, ['/manifest.json', '/chatwoot-sw.js', '/sw.js']);

            if ($isStaticPath) {
                $laravelResponse->header('Cache-Control', 'public, max-age=3600');
                $laravelResponse->headers->remove('Pragma');
                $laravelResponse->headers->remove('Expires');
            } else {
                $laravelResponse->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $laravelResponse->header('Pragma', 'no-cache');
                $laravelResponse->header('Expires', '0');
            }

            // 5. REESCRITURA DE COOKIES (Sin dominio para que localhost las acepte)
            $setCookies = $response->toPsrResponse()->getHeader('Set-Cookie');
            foreach ($setCookies as $cookie) {
                // Quitamos Domain para que el navegador acepte la cookie en host local
                $cleanCookie = preg_replace('/;\s*domain=[^;]+/i', '', $cookie) ?? $cookie;
                // Si estamos en http local, la bandera Secure impide guardar/enviar cookie
                if (!$request->isSecure()) {
                    $cleanCookie = preg_replace('/;\s*secure/i', '', $cleanCookie) ?? $cleanCookie;
                }
                $laravelResponse->headers->set('Set-Cookie', $cleanCookie, false);
            }

            // 6. GESTIÓN DE REDIRECCIONES
            if ($isRedirect) {
                $location = $response->header('Location');
                if (is_string($location) && $location !== '') {
                    $localLocation = str_replace(
                        [$this->chatwootUrl, 'https://' . $this->chatwootHost, 'http://' . $this->chatwootHost],
                        '',
                        $location
                    );
                    if ($localLocation === '') {
                        $localLocation = '/';
                    }
                    $laravelResponse->headers->set('Location', $localLocation, true);
                }
            }

            return $laravelResponse;

        } catch (\Exception $e) {
            Log::error("[Chatwoot Proxy] Error en {$uri}: " . $e->getMessage());
            return response("Error de conexión con Chatwoot", 502);
        }
    }
}
