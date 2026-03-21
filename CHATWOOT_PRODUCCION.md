# Chatwoot en Produccion (Iframe + Proxy)

## 1) Requisitos clave
- Servir la app en `https`.
- Mantener rutas proxied de Chatwoot (`/app`, `/auth`, `/api/v1`, `/assets`, `/brand-assets`, `/vite`, `/packs`, `/storage`, `/sw.js`, `/chatwoot-sw.js`).
- Permitir WebSocket para `/cable`.

## 2) Nginx (recomendado)
```nginx
location /cable {
    proxy_pass https://chatwoot.servilutioncrm.cloud/cable;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host chatwoot.servilutioncrm.cloud;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Host chatwoot.servilutioncrm.cloud;
    proxy_set_header X-Forwarded-Port 443;
}
```

## 3) Apache
```apache
ProxyPreserveHost Off
SSLProxyEngine On

RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteRule ^/cable/?(.*)$  wss://chatwoot.servilutioncrm.cloud/cable/$1 [P,L]
RewriteCond %{HTTP:Upgrade} !=websocket [NC]
RewriteRule ^/cable/?(.*)$  https://chatwoot.servilutioncrm.cloud/cable/$1 [P,L]
```

## 4) Checklist de validacion
- `/app/login` responde 200.
- `/brand-assets/logo.svg` responde 200.
- `/chatwoot-sw.js` responde 200.
- En navegador, el iframe NO navega a `https://chatwoot.servilutioncrm.cloud/...` como documento principal.
- En consola, el socket usa `wss://chatwoot.servilutioncrm.cloud/cable` o `/cable` con upgrade correcto en proxy de servidor.
