# 🎓 GUIDE PÉDAGOGIQUE - API BANQUE LARAVEL


LISTE DES COMPTES: On doit pas y voir des comptes bloqués ou supprimer
DETAIL COMPTE
MIS A JOUR COMPTE 
SUPPRIMER COMPTE
CREER COMPTE 
ARCHIVER/DESARCHIVER COMPTE
BLOQUER/DEBLOQUER COMPTE : ON NE BLOQUE QUE LES COMPTES EPARGNES


Nous allons nous concentrer sur **l'US 2.0 : Lister tous les comptes** avec tous ses scénarios :
- Admin peut récupérer la liste de tous les comptes
- Client peut récupérer la liste de ses comptes

**Base URL :** `http://127.0.0.1:8000/api/v1`

---

## 📚 PRÉPARATION DE VOTRE ENVIRONNEMENT DE TRAVAIL

### Étape 1 : Démarrer votre serveur Laravel
Ouvrez un terminal et exécutez cette commande :
```bash
php artisan serve --host=127.0.0.1 --port=8000
```
**Résultat attendu :** Le serveur démarre et affiche `Starting Laravel development server: http://127.0.0.1:8000`

### Étape 2 : Préparer Postman
1. **Lancez Postman**
2. **Créez une nouvelle collection** nommée **"API Banque - Tests US 2.0"**
3. **Créez un environnement** nommé **"API Banque Dev"** avec ces variables :
   - `base_url` = `http://127.0.0.1:8000/api/v1`
   - `token` = (laisser vide pour l'instant)

### Étape 3 : Headers par défaut
Pour chaque requête, ajoutez toujours ces headers :
- `Accept`: `application/json`
- `Content-Type`: `application/json`

---

## 🔐 AUTHENTIFICATION - PREMIÈRE ÉTAPE OBLIGATOIRE

**Avant de pouvoir tester l'US 2.0, vous devez vous authentifier !**

### 🔑 Connexion en tant qu'Admin

**L'admin peut se connecter sans code de sécurité :**

**Dans Postman :**
1. **Nouvelle requête** dans votre collection
2. **Méthode** : `POST`
3. **URL** : `{{base_url}}/auth/login`
4. **Body** (raw → JSON) :
```json
{
  "email": "admin@banque.com",
  "password": "admin123"
}
```

**Cliquez sur "Send"**

**Réponse attendue :**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Administrateur",
      "email": "admin@banque.com"
    },
    "access_token": "1|admin_token_here...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**En tant qu'admin, vous pouvez voir TOUS les comptes de la banque !**

### 👤 Connexion en tant que Client

**Les clients doivent fournir un code de sécurité pour la première connexion :**

**Exemple concret avec le Client 1 : Amadou Diallo**

**Dans Postman :**
1. **Nouvelle requête** dans votre collection
2. **Méthode** : `POST`
3. **URL** : `{{base_url}}/auth/login`
4. **Body** (raw → JSON) :
```json
{
  "email": "amadou.diallo@example.com",
  "password": "password123",
  "code": "ABC123"
}
```

**Cliquez sur "Send"**

**Que se passe-t-il ?**
- Laravel vérifie l'email et le mot de passe
- Pour la première connexion, il vérifie aussi le code de sécurité
- Si tout est correct, il génère un token d'accès

**Réponse attendue :**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "titulaire": "Amadou Diallo",
      "email": "amadou.diallo@example.com",
      "telephone": "+221771234567"
    },
    "access_token": "1|abc123def456...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Action importante :**
1. Copiez le `access_token` de la réponse
2. Collez-le dans la variable `token` de votre environnement Postman
3. **Toutes vos futures requêtes** devront avoir le header : `Authorization: Bearer {{token}}`

**En tant que client, vous ne verrez que vos propres comptes.**

---

## 🎯 US 2.0 : LISTER TOUS LES COMPTES - ANALYSE DÉTAILLÉE

Maintenant que vous êtes authentifié, testons l'endpoint principal de l'US 2.0 !

### Scénario 1 : Client récupère ses propres comptes

**Règle métier :** Un client ne voit que ses propres comptes, pas ceux des autres clients.

**Dans Postman :**
- **Méthode** : `GET`
- **URL** : `{{base_url}}/comptes`
- **Header** : `Authorization: Bearer {{token}}`

**Cliquez sur "Send"**

**Que se passe-t-il dans le code ?**
1. Le middleware `auth:api` vérifie votre token
2. Le middleware `RoleMiddleware` détermine que vous êtes un `Client`
3. Dans `CompteController@index()`, la ligne :
   ```php
   if ($user instanceof Client) {
       $query->where('client_id', $user->id); // Filtre automatique !
   }
   ```
   Cette ligne filtre automatiquement pour ne montrer que les comptes du client connecté.

**Résultat :** Vous ne verrez que les comptes d'Amadou Diallo (environ 2-3 comptes).

### Scénario 2 : Tester avec un autre client

**Déconnectez-vous d'abord :**
- **Méthode** : `POST`
- **URL** : `{{base_url}}/auth/logout`
- **Header** : `Authorization: Bearer {{token}}`

**Reconnectez-vous avec Client 2 : Fatou Sow**
```json
{
  "email": "fatou.sow@example.com",
  "password": "password123",
  "code": "DEF456"
}
```

**Testez à nouveau :**
- **GET** `{{base_url}}/comptes`
- **Header** : `Authorization: Bearer {{token}}`

**Résultat :** Vous verrez maintenant les comptes de Fatou Sow uniquement !

**Leçon importante :** L'isolation des données fonctionne parfaitement. Chaque client ne voit que ses comptes.

### Scénario 3 : Pagination et limites

**Testez la pagination :**
- **URL** : `{{base_url}}/comptes?page=1&limit=2`

**Résultat :** Seulement 2 comptes par page, avec des métadonnées de pagination.

### Scénario 4 : Filtres avancés

**Filtrer par statut actif :**
- **URL** : `{{base_url}}/comptes?statut=actif`

**Filtrer par type épargne :**
- **URL** : `{{base_url}}/comptes?type=epargne`

**Combiner les filtres :**
- **URL** : `{{base_url}}/comptes?statut=actif&type=epargne&limit=5`

### Scénario 5 : Recherche par nom

**Rechercher vos propres comptes :**
- **URL** : `{{base_url}}/comptes?search=Sow`

**Que fait cette recherche ?**
Dans le code, la méthode `recherche()` :
```php
public function scopeRecherche($query, $search) {
    return $query->where(function ($q) use ($search) {
        $q->where('numero_compte', 'like', "%{$search}%")
          ->orWhereHas('client', function ($clientQuery) use ($search) {
              $clientQuery->where('titulaire', 'like', "%{$search}%");
          });
    });
}
```

### Scénario 6 : Tri des résultats

**Trier par solde décroissant :**
- **URL** : `{{base_url}}/comptes?sort=solde&order=desc`

**Trier par date de création :**
- **URL** : `{{base_url}}/comptes?sort=dateCreation&order=asc`

---

## 🔍 SCÉNARIO ADMIN - MAINTENANT DISPONIBLE !

**L'admin peut maintenant voir TOUS les comptes de la banque !**

### Testez avec l'admin :

**Connectez-vous en tant qu'admin :**
```json
{
  "email": "admin@banque.com",
  "password": "admin123"
}
```

**Puis testez :**
- **GET** `{{base_url}}/comptes`
- **Header** : `Authorization: Bearer {{token}}`

**Résultat :** Vous verrez TOUS les comptes de TOUS les clients (pas de filtrage automatique).

**Que se passe-t-il dans le code ?**
Dans `CompteController@index()`, la condition :
```php
if ($user instanceof Client) {
    $query->where('client_id', $user->id); // Filtre SEULEMENT pour les clients
}
```
Cette ligne ne s'applique qu'aux `Client`, donc l'admin voit tout !

---

## 📖 COMPRENDRE SWAGGER UI - VOTRE ALLIÉ PÉDAGOGIQUE

**Swagger UI est votre meilleur ami pour comprendre et tester l'API !**

### Comment accéder à Swagger UI

1. **Votre serveur doit tourner** (`php artisan serve`)
2. **Ouvrez votre navigateur**
3. **Allez à l'URL :** `http://127.0.0.1:8000/api/documentation`

### Interface Swagger - Guide pas à pas

#### 1. **Page d'accueil Swagger**
- Vous voyez le titre : "API Banque - Documentation"
- Version : "1.0.0"
- Description de l'API

#### 2. **Authentification dans Swagger**
- Cliquez sur le bouton **"Authorize"** (en haut à droite)
- Dans la fenêtre qui s'ouvre, entrez : `Bearer VOTRE_TOKEN`
- **Important :** Remplacez `VOTRE_TOKEN` par le token réel que vous avez récupéré avec Postman ou par l'authentification depuis l'interface de swagger

#### 3. **Explorer les endpoints**

**Cliquez sur "Comptes" dans le menu gauche**

Vous verrez tous les endpoints liés aux comptes :

- **GET /api/v1/comptes** ← C'est l'US 2.0 !
- GET /api/v1/comptes/{compteId}
- POST /api/v1/comptes
- etc.

#### 4. **Connexion via Swagger UI (Authentification)**

**Pour l'admin (pas de code requis) :**
1. **Cliquez sur la ligne** `POST /api/v1/auth/login`
2. **La documentation se déroule** - lisez-la attentivement !
3. **Cliquez sur "Try it out"** (bouton bleu en haut à droite)
4. **Remplissez le body :**
   ```json
   {
     "email": "admin@banque.com",
     "password": "admin123"
   }
   ```
   **Note :** Pas de champ "code" pour l'admin !

5. **Cliquez sur "Execute"** (bouton vert en bas)

**Résultat :**
- **Request URL** : `http://127.0.0.1:8000/api/v1/auth/login`
- **Server response** : Code 200 avec le token JWT

**⚠️ Important :** Dans Swagger UI, vérifiez que le serveur sélectionné est `http://127.0.0.1:8000` (menu déroulant en haut). Si c'est `http://api.moustapha.seck.com`, changez-le !

**Copiez le token de la réponse (sans "Bearer") et utilisez-le pour autoriser les futures requêtes !**

**⚠️ Dans Swagger UI, collez simplement le token brut (sans "Bearer") - Swagger ajoutera automatiquement le préfixe !**

**Exemple de token à coller :**
```
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiZjQ2Y2M3ZmNjYjQ2ZWQxNDFmMDE0YjkxNDY3NTBmYTEyMDRiYjM0ZjliNTM3YmJlYjJlMTdmNDA2OTMwNjhmNDAzODAzYzU5YWEzMzRmMWIiLCJpYXQiOjE3NjE0NTI4OTUuNDk3MzQ3LCJuYmYiOjE3NjE0NTI4OTUuNDk3MzQ3LCJleHAiOjE3NzcxNzc2OTUuNDk0NTAxLCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.dsRSvqAJrKjI9hKq7vfLa7uC05SvFgnMttGYHn8xpbH0eMhya_sZavOlGWsiAFcYcPnAGB59tm8JR63XQYwB3tuOJCs66bMey7jh_sXE9QZ2r2uRIoMOfO2TpJH6NyxFxuGJVv1rxul2Jy_auMYTgaCCOAEebPjt6fNNJ4iQHBy1vz4gm5dypkS683eknSN3IYPzz4oqa95vxbWDAfcYntbjCyggRiRofHtfNewaKzG-0T6dQk7NaBTxJApgPNCjq14uVa_TwS1xtdN2f9o8f2Y-Nimimr6FgskCsNHu_VXkpsGPD0HWfXjg6OUNvbqsjscYByUsdLItRUEflzhqjr_N-KkNKcFdA2ox-fTmf1BE03Ihjbk9E9_2sRuUZFIBTSY_nRW9Li91xjg6b4h-tteGyQXzrRVXjDKg0qUdQgY_yswbel2MW8OyWgZVcOjSBeqnZEfHR4wd_6ZvLZt6Yj8w-yrwcIxdB_-TGm60cDyzXxeWEPLDZn3OcFTznKtVX3Wj_urzX8sES8QJgjaaQl5duf1WCZKCHyeSjG1UiyKWM4luFHbaHiJN1IGfOq7FRMNot-GdiR_LQh0WVEUrkyz9P6t9qqOIfy_ShtjttuOhAWVxIDsjh7LiKfgiTU_zSM3ZFaO6fxooU7Z5ITCmpR3CaM1xaG1bfdahJHTI_W4
```

#### 5. **Autoriser Swagger avec le token JWT**

**Avant de tester les endpoints protégés, vous devez autoriser Swagger :**

1. **Cliquez sur le bouton "Authorize"** (en haut à droite, icône cadenas)
2. **Dans la fenêtre popup :**
   - **Value** : Collez votre token JWT (sans "Bearer")
   - **Cliquez sur "Authorize"**
3. **Fermez la popup**

**Maintenant Swagger est autorisé pour tous les endpoints protégés !**

#### 6. **Tester GET /api/v1/comptes dans Swagger**

1. **Cliquez sur la ligne** `GET /api/v1/comptes`
2. **La documentation se déroule** - lisez-la attentivement !
3. **Cliquez sur "Try it out"** (bouton bleu en haut à droite)
4. **Paramètres optionnels :**
   - page: 1
   - limit: 10
   - type: (vide)
   - statut: (vide)
   - search: (vide)
   - sort: dateCreation
   - order: desc

5. **Cliquez sur "Execute"** (bouton vert en bas)

**Résultat :**
- **Request URL** : `http://127.0.0.1:8000/api/v1/comptes`
- **Server response** : Code 200 avec vos données JSON

**⚠️ Rappel :** Assurez-vous que le serveur `http://127.0.0.1:8000` est sélectionné dans Swagger UI !

#### 5. **Comprendre la réponse Swagger**

Swagger vous montre :
- **Curl** : La commande curl équivalente
- **Request URL** : L'URL complète appelée
- **Response body** : Le JSON retourné
- **Response code** : 200 (succès)

#### 6. **Tester les paramètres dans Swagger**

**Essayez différents paramètres :**
- `statut: actif` → Voir seulement les comptes actifs
- `type: epargne` → Voir seulement les comptes épargne
- `limit: 3` → Voir seulement 3 comptes
- `search: Diallo` → Rechercher par nom

#### 7. **Comprendre les erreurs dans Swagger**

**Testez une erreur :**
1. **Déconnectez-vous** dans Swagger (bouton "Logout" dans Authorize)
2. **Relancez la requête GET /api/v1/comptes**
3. **Résultat :** Erreur 401 Unauthorized

**Leçon :** Swagger montre clairement les codes d'erreur et leurs significations.

---

## 🧪 EXERCICES PRATIQUES - METTEZ EN PRATIQUE !

### Exercice 1 : Exploration complète des comptes d'un client
1. Connectez-vous avec `amadou.diallo@example.com`
2. Listez tous ses comptes
3. Filtrez par comptes actifs
4. Filtrez par comptes épargne
5. Recherchez "Diallo"
6. Triez par solde décroissant

### Exercice 2 : Comparaison entre clients
1. Connectez-vous avec Client 1 → Notez le nombre de comptes
2. Connectez-vous avec Client 2 → Notez le nombre de comptes
3. Vérifiez qu'ils sont différents (isolation des données)

### Exercice 5 : Pouvoirs de l'admin vs client
1. Connectez-vous en tant qu'admin → Notez le nombre TOTAL de comptes
2. Connectez-vous avec un client → Notez le nombre de comptes du client
3. Vérifiez que l'admin voit beaucoup plus de comptes
4. Testez les filtres en tant qu'admin (devrait fonctionner sur tous les comptes)

### Exercice 3 : Maîtrise de Swagger
1. Ouvrez Swagger UI
2. Autorisez-vous avec votre token
3. Testez tous les paramètres de filtrage
4. Observez les réponses JSON
5. Testez une requête sans autorisation

### Exercice 4 : Comprendre la pagination
1. Listez les comptes avec `limit=2`
2. Notez les métadonnées de pagination
3. Testez la page 2
4. Vérifiez les liens `next` et `prev`

---

## 📊 VOS UTILISATEURS DE TEST

### 👑 Administrateur
| Rôle | Email | Mot de passe | Code requis |
|------|-------|--------------|-------------|
| Admin | `admin@banque.com` | `admin123` | ❌ Non |

**L'admin voit TOUS les comptes de la banque.**

### 👥 Clients
| Client | Email | Mot de passe | Code | Nombre de comptes |
|--------|-------|--------------|------|-------------------|
| Amadou Diallo | `amadou.diallo@example.com` | `password123` | `ABC123` | 2-3 |
| Fatou Sow | `fatou.sow@example.com` | `password123` | `DEF456` | 2-3 |
| Moussa Ndiaye | `moussa.ndiaye@example.com` | `password123` | `GHI789` | 2-3 |
| Aïssatou Ba | `aissatou.ba@example.com` | `password123` | `JKL012` | 2-3 |
| Cheikh Sy | `cheikh.sy@example.com` | `password123` | `MNO345` | 2-3 |

**Les clients ne voient que leurs propres comptes.**

---

## 🎓 RÉSUMÉ DES APPRENTISSAGES

**Ce que vous avez appris :**

1. **Authentification Laravel** : Token Bearer, middleware d'authentification
2. **Autorisation et rôles** : Différenciation Admin/Client
3. **Filtrage automatique** : Sécurité des données par utilisateur
4. **API RESTful** : GET avec paramètres de requête
5. **Pagination** : Gestion des gros volumes de données
6. **Swagger UI** : Documentation interactive et tests intégrés
7. **Isolation des données** : Chaque client ne voit que ses comptes

**Prochaine étape :** Implémentez le rôle Admin pour voir tous les comptes !

---

**Bravo ! Vous maîtrisez maintenant parfaitement l'US 2.0 de votre API Banque ! 🎉**

**Questions fréquentes :**
- **"Pourquoi je ne vois pas tous les comptes ?"** → Normal ! En tant que Client, vous ne voyez que vos comptes.
- **"Swagger ne marche pas ?"** → Vérifiez que votre serveur tourne et que vous êtes autorisé.
- **"Token expiré ?"** → Refaites un login pour obtenir un nouveau token.

**N'hésitez pas à me poser des questions si quelque chose ne fonctionne pas !** 👨‍🏫
