
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

# Créer un fichier .env temporaire avec les variables d'environnement
echo "📝 Création du fichier .env temporaire..."

# Créer le fichier .env manuellement avec les bonnes valeurs
cat > .env << EOF
APP_NAME="${APP_NAME:-moustapha-seck}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-https://seck-moustapha-sn.onrender.com}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-dpg-d40eu76r433s738b86ig-a}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-banque_api_iiop}"
DB_USERNAME="${DB_USERNAME:-banque_user}"
DB_PASSWORD="${DB_PASSWORD:-B8nSoFPHv3raL3pZWafjdpb9h3uf0rvv}"

CACHE_DRIVER="${CACHE_DRIVER:-file}"
SESSION_DRIVER="${SESSION_DRIVER:-file}"

MAIL_MAILER="${MAIL_MAILER:-smtp}"
MAIL_HOST="${MAIL_HOST:-smtp.gmail.com}"
MAIL_PORT="${MAIL_PORT:-587}"
MAIL_USERNAME="${MAIL_USERNAME:-seckmoustapha238@gmail.com}"
MAIL_PASSWORD="${MAIL_PASSWORD:-fwmd dvos uelp fxuj}"
MAIL_ENCRYPTION="${MAIL_ENCRYPTION:-tls}"
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-seckmoustapha238@gmail.com}"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-API Banque}"

PASSPORT_PRIVATE_KEY="${PASSPORT_PRIVATE_KEY}"
PASSPORT_PUBLIC_KEY="${PASSPORT_PUBLIC_KEY}"
EOF

# Afficher le contenu du fichier .env pour debug
echo "📄 Contenu du fichier .env créé :"
cat .env

echo "✅ Fichier .env créé"

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
    echo "✅ Base de données déjà seedée, vérification des données..."
    # Vérifier si les données existent déjà
    ADMIN_COUNT=$(php artisan tinker --execute="echo App\Models\Admin::count();" 2>/dev/null || echo "0")
    CLIENT_COUNT=$(php artisan tinker --execute="echo App\Models\Client::count();" 2>/dev/null || echo "0")
    COMPTE_COUNT=$(php artisan tinker --execute="echo App\Models\Compte::count();" 2>/dev/null || echo "0")

    if [ "$ADMIN_COUNT" = "0" ] || [ "$CLIENT_COUNT" = "0" ] || [ "$COMPTE_COUNT" = "0" ]; then
        echo "⚠️ Données manquantes, re-exécution des seeders..."
        php artisan db:seed --force --no-interaction || echo "⚠️ Seeders échoués, continuation..."
    else
        echo "✅ Données déjà présentes: $ADMIN_COUNT admins, $CLIENT_COUNT clients, $COMPTE_COUNT comptes"
    fi
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
