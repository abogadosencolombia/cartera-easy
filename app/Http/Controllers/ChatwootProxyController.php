<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootProxyController extends Controller
{
    private string $chatwootUrl;
    private string $chatwootHost;

    public function __construct()
    {
        $this->chatwootUrl = rtrim((string) config('services.chatwoot.url'), '/');
        $this->chatwootHost = parse_url($this->chatwootUrl, PHP_URL_HOST) ?: 'chatwoot.servilutioncrm.cloud';
    }

    public function proxy(Request $request)
    {
        $uri = $request->getRequestUri();
        $proxyUri = $uri === '/chatwoot-sw.js' ? '/sw.js' : $uri;
        $fullUrl = $this->chatwootUrl . $proxyUri;
        $method = $request->method();

        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            $keyLower = strtolower($key);
            if (in_array($keyLower, ['host', 'content-length', 'connection', 'accept-encoding'], true)) {
                continue;
            }
            $headers[$key] = $value[0];
        }

        $headers['Host'] = $this->chatwootHost;
        $headers['X-Forwarded-Proto'] = 'https';

        try {
            $pendingRequest = Http::withHeaders($headers)->withOptions([
                'verify' => (bool) config('services.chatwoot.proxy_verify_tls', true),
                'allow_redirects' => true,
            ]);

            if (! in_array($method, ['GET', 'HEAD'], true)) {
                $pendingRequest->withBody($request->getContent(), $request->header('Content-Type') ?? 'application/json');
            }

            $response = $pendingRequest->send($method, $fullUrl);

            $contentType = $response->header('Content-Type');
            $body = $response->body();

            if (is_string($contentType) && (str_contains($contentType, 'html') || str_contains($contentType, 'javascript') || str_contains($contentType, 'json'))) {
                $localBase = $request->getSchemeAndHttpHost();
                $body = str_replace($this->chatwootUrl, $localBase, $body);
                $body = str_replace($this->chatwootHost, $request->getHttpHost(), $body);
            }

            $laravelResponse = response($body, $response->status());

            foreach ($response->headers() as $name => $values) {
                if (in_array(strtolower($name), ['content-length', 'transfer-encoding', 'content-encoding', 'connection'], true)) {
                    continue;
                }
                $laravelResponse->header($name, $values[0]);
            }

            $laravelResponse->headers->remove('X-Frame-Options');
            $laravelResponse->headers->remove('Content-Security-Policy');
            $laravelResponse->header('X-Frame-Options', 'ALLOWALL');

            return $laravelResponse;
        } catch (\Exception $e) {
            Log::error('[Chatwoot Proxy] Error crítico: ' . $e->getMessage());
            return response('Error de conexión con Chatwoot', 502);
        }
    }
}
