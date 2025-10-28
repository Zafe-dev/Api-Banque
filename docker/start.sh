
#!/bin/bash
set -e

echo "🚀 Lancement du conteneur Laravel..."

# Réinitialiser tous les caches avant le démarrage
php artisan optimize:clear || true

# Générer la clé Laravel si elle n'existe pas
echo "🔑 Vérification de la clé APP_KEY..."
if [ -z "$APP_KEY" ]; then
    echo "⚙️ Génération d'une nouvelle clé Laravel..."
    php artisan key:generate --force --no-interaction || true
else
    echo "✅ APP_KEY déjà définie dans l'environnement"
fi

# Lancer les migrations si la BDD est dispo
echo "🔄 Exécution des migrations..."
php artisan migrate --force --no-interaction || echo "⚠️ Migrations échouées, continuation..."

# Générer les clés Passport AVANT tout le reste (seulement si elles n'existent pas)
echo "🔐 Vérification des clés Passport..."
mkdir -p storage
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    echo "🔐 Génération des clés Passport..."
    php artisan passport:keys --force || true
else
    echo "✅ Clés Passport déjà présentes"
fi

# Donner toutes les permissions sur le répertoire storage
chmod -R 777 storage
chown -R www-data:www-data storage

# Vérifier que les clés existent et sont lisibles
if [ -f storage/oauth-private.key ] && [ -f storage/oauth-public.key ]; then
    echo "✅ Clés Passport générées et accessibles"
    ls -la storage/oauth-*.key
else
    echo "❌ Problème avec les clés Passport"
fi

# Passport est maintenant géré via les variables d'environnement dans AppServiceProvider
echo "🔐 Passport configuré via variables d'environnement"

# Vérifier si la base a déjà été seedée (une seule fois)
if [ ! -f /data/seeded.flag ]; then
    echo "🌱 Exécution des seeders (première fois)..."
    php artisan db:seed --force --no-interaction || echo "⚠️ Seeders échoués, continuation..."
    mkdir -p /data
    touch /data/seeded.flag
    echo "✅ Base de données seedée et flag créé"
else
    echo "✅ Base de données déjà seedée, passage..."
fi

# Générer la documentation Swagger AVANT les caches
echo "📚 Génération de la documentation Swagger..."
php artisan l5-swagger:generate --no-interaction || true

# Générer les caches pour accélérer l'app (SAUF les routes pour éviter les problèmes avec les Closures)
# php artisan config:cache || true  # Désactivé temporairement pour debug
# php artisan route:cache || true  # Désactivé temporairement à cause des routes Closure
# php artisan view:cache || true   # Désactivé temporairement pour debug

echo "✅ Configuration Laravel terminée ! Démarrage des services..."

# Lancer Nginx + PHP-FPM + Queue Worker
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
