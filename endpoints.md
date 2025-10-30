# 🚀 GUIDE COMPLET - API BANQUE LARAVEL - ENDPOINTS DE PRODUCTION

**Base URL Production :** `https://seck-moustapha-sn.onrender.com/api/v1`

**Base URL Local :** `http://127.0.0.1:8000/api/v1`

---

## 📋 SOMMAIRE DES ENDPOINTS

- ✅ **LISTE DES COMPTES** : Comptes actifs uniquement (bloqués/supprimés cachés)
- ✅ **DÉTAIL COMPTE** : Informations complètes d'un compte
- ✅ **MIS A JOUR COMPTE** : Modification des informations
- ✅ **SUPPRIMER COMPTE** : Soft delete (admin seulement)
- ✅ **CRÉER COMPTE** : Nouveau compte + client (admin seulement)
- ✅ **BLOQUER COMPTE** : Épargne uniquement avec durée
- ✅ **DÉBLOQUER COMPTE** : Remettre en actif
- ✅ **ARCHIVER/DESARCHIVER** : Jobs automatiques

---
## 🔐 AUTHENTIFICATION - ÉTAPE OBLIGATOIRE

### 📝 Connexion Admin
```bash
POST https://seck-moustapha-sn.onrender.com/api/v1/auth/login
```

**Headers :**
```
Content-Type: application/json
Accept: application/json
```

**Body :**
```json
{
  "email": "admin1@banque.com",
  "password": "admin123"
}
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "user": {
      "id": 2,
      "email": "admin1@banque.com",
      "name": "Administrateur 1"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  
}
```

### 📝 Connexion Client
```bash
POST https://seck-moustapha-sn.onrender.com/api/v1/auth/login
```

**Body :**
```json
{
  "email": "fatou.sow@example.com",
  "password": "password123",
  "code": "DEF456"
}
```

---

## ✅ LISTE DES COMPTES

**Admin voit tous les comptes actifs**
**Client voit seulement ses comptes actifs**
**Comptes bloqués et supprimés sont cachés**

```bash
GET https://seck-moustapha-sn.onrender.com/api/v1/comptes
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Accept: application/json
```

**Paramètres optionnels :**
- `page=1` (pagination)
- `limit=10` (éléments par page)
- `type=epargne|cheque` (filtre par type)
- `statut=actif|bloque|ferme` (filtre par statut)
- `search=terme` (recherche par titulaire ou numéro)
- `sort=dateCreation|solde|titulaire` (tri)
- `order=asc|desc` (ordre)

**✅ Réponse :**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "numeroCompte": "C00123456",
      "titulaire": "Nom Client",
      "type": "epargne",
      "solde": 100000.00,
      "devise": "FCFA",
      "dateCreation": "2025-10-29T04:00:00.000000Z",
      "statut": "actif"
    }
  ],
  "pagination": {
    "currentPage": 1,
    "totalPages": 67,
    "totalItems": 664,
    "itemsPerPage": 10
  }
}
```

---

## ✅ DÉTAIL COMPTE

```bash
GET https://seck-moustapha-sn.onrender.com/api/v1/comptes/{compteId}
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Accept: application/json
```

**✅ Réponse :**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123456",
    "titulaire": "Nom Client",
    "type": "epargne",
    "solde": 100000.00,
    "devise": "FCFA",
    "dateCreation": "2025-10-29T04:00:00.000000Z",
    "statut": "actif",
    "motifBlocage": null,
    "dateDebutBlocage": null,
    "dateFinBlocage": null,
    "metadata": {
      "derniereModification": "2025-10-29T04:00:00.000000Z",
      "version": 1
    }
  }
}
```

---

## ✅ CRÉER COMPTE

```bash
POST https://seck-moustapha-sn.onrender.com/api/v1/comptes
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body :**
```json
{
  "type": "epargne",
  "soldeInitial": 50000,
  "devise": "FCFA",
  "client": {
    "id": "uuid-client-existant",
    "titulaire": "Nouveau Client",
    "email": "nouveau@example.com",
    "telephone": "+221771234567",
    "adresse": "Dakar, Sénégal"
  }
}
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Compte créé avec succès",
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123457",
    "titulaire": "Nouveau Client",
    "type": "epargne",
    "solde": 50000.00,
    "devise": "FCFA",
    "dateCreation": "2025-10-29T04:00:00.000000Z",
    "statut": "actif"
  }
}
```

---

## ✅ MIS A JOUR COMPTE

```bash
PATCH https://seck-moustapha-sn.onrender.com/api/v1/comptes/{compteId}
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body :**
```json
{
  "titulaire": "Nouveau Nom",
  "informationsClient": {
    "telephone": "+221778765432",
    "email": "nouveau@email.com"
  }
}
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Compte mis à jour avec succès",
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123456",
    "titulaire": "Nouveau Nom",
    "type": "epargne",
    "solde": 100000.00,
    "devise": "FCFA",
    "statut": "actif"
  }
}
```

---

## ✅ SUPPRIMER COMPTE (Soft Delete)

```bash
DELETE https://seck-moustapha-sn.onrender.com/api/v1/comptes/{compteId}
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Accept: application/json
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Compte supprimé avec succès",
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123456",
    "statut": "ferme",
    "dateFermeture": "2025-10-29T04:00:00.000000Z"
  }
}
```

---

## ✅ BLOQUER COMPTE (Épargne uniquement)

```bash
POST https://seck-moustapha-sn.onrender.com/api/v1/comptes/{compteId}/bloquer
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body :**
```json
{
  "motif": "Suspicion de fraude",
  "duree": 30,
  "unite": "jours"
}
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Compte bloqué avec succès",
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123456",
    "statut": "bloque",
    "motifBlocage": "Suspicion de fraude",
    "dateDebutBlocage": "2025-10-29T04:00:00.000000Z",
    "dateFinBlocage": "2025-11-28T04:00:00.000000Z"
  }
}
```

---

## ✅ DÉBLOQUER COMPTE

```bash
POST https://seck-moustapha-sn.onrender.com/api/v1/comptes/{compteId}/debloquer
```

**Headers :**
```
Authorization: Bearer VOTRE_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body :**
```json
{
  "motif": "Blocage levé après vérification"
}
```

**✅ Réponse :**
```json
{
  "success": true,
  "message": "Compte débloqué avec succès",
  "data": {
    "id": "uuid",
    "numeroCompte": "C00123456",
    "statut": "actif",
    "motifBlocage": null,
    "dateDebutBlocage": null,
    "dateFinBlocage": null
  }
}
```

---

## 📊 UTILISATEURS DE TEST

### 👑 Administrateurs
| Email | Mot de passe | Description |
|-------|--------------|-------------|
| `admin1@banque.com` | `admin123` | Admin principal |
| `admin2@banque.com` | `admin123` | Admin secondaire |
| `admin3@banque.com` | `admin123` | Admin tertiaire |

### 👥 Clients (avec codes de vérification)
| Email | Mot de passe | Code | Nombre comptes |
|-------|--------------|------|----------------|
| `fatou.sow@example.com` | `password123` | `DEF456` | 2-3 |
| `moussa.ndiaye@example.com` | `password123` | `GHI789` | 2-3 |
| `aissatou.ba@example.com` | `password123` | `JKL012` | 2-3 |
| `cheikh.sy@example.com` | `password123` | `MNO345` | 2-3 |

---

## 🧪 GUIDE POSTMAN - TESTS COMPLÈTS

### 📋 **COLLECTION POSTMAN À IMPORTER**

```json
{
  "info": {
    "name": "API Banque Laravel",
    "description": "Collection complète pour tester l'API Banque"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://127.0.0.1:8000/api/v1",
      "type": "string"
    },
    {
      "key": "token",
      "value": "",
      "type": "string"
    }
  ]
}
```

### 🔐 **1. AUTHENTIFICATION**

#### **Connexion Admin**
```
Method: POST
URL: {{base_url}}/auth/login
Headers:
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "email": "admin1@banque.com",
  "password": "admin123"
}
```

#### **Connexion Client**
```
Method: POST
URL: {{base_url}}/auth/login
Headers:
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "email": "fatou.sow@example.com",
  "password": "password123",
  "code": "DEF456"
}
```

### 📋 **2. LISTE DES COMPTES**

```
Method: GET
URL: {{base_url}}/comptes?page=1&limit=10
Headers:
  Authorization: Bearer {{token}}
  Accept: application/json

Tests (Postman):
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
pm.test("Response has success", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.success).to.eql(true);
});
pm.test("No blocked accounts shown", function () {
    var jsonData = pm.response.json();
    jsonData.data.forEach(function(compte) {
        pm.expect(compte.statut).to.not.eql("bloque");
        pm.expect(compte.statut).to.not.eql("ferme");
    });
});
```

### 👁️ **3. DÉTAIL COMPTE**

```
Method: GET
URL: {{base_url}}/comptes/{compte_id}
Headers:
  Authorization: Bearer {{token}}
  Accept: application/json

Variables: compte_id = 550e8400-e29b-41d4-a716-446655440012
```

### ✏️ **4. MISE À JOUR COMPTE**

```
Method: PATCH
URL: {{base_url}}/comptes/{compte_id}
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "solde": 3000000
}

Variables: compte_id = 550e8400-e29b-41d4-a716-446655440012
```

### 🚫 **5. BLOQUER COMPTE (Épargne uniquement)**

```
Method: POST
URL: {{base_url}}/comptes/{compte_id}/bloquer
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "motif": "Test blocage automatique",
  "duree": 7,
  "unite": "jours"
}

Variables: compte_id = 550e8400-e29b-41d4-a716-446655440012
```

### ✅ **6. DÉBLOQUER COMPTE**

```
Method: POST
URL: {{base_url}}/comptes/{compte_id}/debloquer
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "motif": "Test déblocage"
}

Variables: compte_id = 550e8400-e29b-41d4-a716-446655440012
```

### 🆕 **7. CRÉER COMPTE (Admin seulement)**

```
Method: POST
URL: {{base_url}}/comptes
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "type": "epargne",
  "soldeInitial": 50000,
  "devise": "FCFA",
  "client": {
    "titulaire": "Nouveau Client Test",
    "email": "nouveau$(date +%s)@test.com",
    "telephone": "+221771234567"
  }
}
```

### 🗑️ **8. SUPPRIMER COMPTE (Admin seulement)**

```
Method: DELETE
URL: {{base_url}}/comptes/{compte_id}
Headers:
  Authorization: Bearer {{token}}
  Accept: application/json

Variables: compte_id = {id_du_compte_cree}
```

### 🔄 **9. TESTS AVANCÉS**

#### **Filtrage par type**
```
GET {{base_url}}/comptes?type=epargne
Authorization: Bearer {{token}}
```

#### **Filtrage par statut**
```
GET {{base_url}}/comptes?statut=actif
Authorization: Bearer {{token}}
```

#### **Recherche**
```
GET {{base_url}}/comptes?search=Fatou
Authorization: Bearer {{token}}
```

#### **Tri**
```
GET {{base_url}}/comptes?sort=solde&order=desc
Authorization: Bearer {{token}}
```

### 📊 **UTILISATEURS DE TEST**

| Type | Email | Mot de passe | Code |
|------|-------|--------------|------|
| Admin | `admin1@banque.com` | `admin123` | - |
| Client | `fatou.sow@example.com` | `password123` | `DEF456` |

### ⚡ **COMMANDES CURL RAPIDES**

```bash
# 1. Login Admin
TOKEN=$(curl -s -X POST "http://127.0.0.1:8000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin1@banque.com","password":"admin123"}' | jq -r '.data.access_token')

# 2. Liste comptes
curl -X GET "http://127.0.0.1:8000/api/v1/comptes" \
  -H "Authorization: Bearer $TOKEN"

# 3. Créer compte
curl -X POST "http://127.0.0.1:8000/api/v1/comptes" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"epargne","soldeInitial":100000,"devise":"FCFA","client":{"titulaire":"Test","email":"test@example.com","telephone":"+221771234567"}}'

# 4. Bloquer compte (remplacer ID)
curl -X POST "http://127.0.0.1:8000/api/v1/comptes/{ID}/bloquer" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"motif":"Test","duree":7,"unite":"jours"}'
```

---

## ⚠️ RÈGLES MÉTIER IMPORTANTES

1. **Comptes cachés :** Les comptes avec `statut = 'bloque'` ou `statut = 'ferme'` ne sont jamais affichés dans la liste
2. **Blocage restreint :** Seuls les comptes de type `'epargne'` peuvent être bloqués
3. **Isolation données :** Les clients ne voient que leurs propres comptes actifs
4. **Admins voient tout :** Les administrateurs voient tous les comptes actifs de tous les clients
5. **Soft delete :** La suppression marque le compte comme `'ferme'` sans le supprimer physiquement

---

## 🎯 ÉTATS POSSIBLES DES COMPTES

- **`actif`** : Compte normal, visible dans les listes
- **`bloque`** : Compte bloqué temporairement, caché des listes
- **`ferme`** : Compte supprimé (soft delete), caché des listes

---

**🎉 Votre API est maintenant complètement fonctionnelle en production !**

**Testez tous les endpoints avec Postman ou curl pour valider le comportement.** 🚀

