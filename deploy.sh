#!/bin/bash
set -e

echo "🚀 Iniciando despliegue..."

# 1. Cambiar al directorio del código
cd /code

# 2. Obtener la última versión del código
echo "📥 Descargando cambios desde Git..."
git pull origin main

# 3. Instalar dependencias de PHP
echo "📦 Instalando dependencias de PHP..."
composer install --optimize-autoloader --no-dev

# 4. Instalar dependencias de JavaScript y compilar assets
echo "🎨 Compilando assets..."
npm install
npm run build

# 5. Ejecutar migraciones
echo "🗄️ Ejecutando migraciones..."
php artisan migrate --force

# 6. Limpiar y optimizar caché
echo "🧹 Limpiando caché..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "⚡ Optimizando aplicación..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Arreglar permisos
echo "🔒 Configurando permisos..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Despliegue completado exitosamente"