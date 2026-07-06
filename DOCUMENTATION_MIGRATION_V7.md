# Mikhmonv2 – Guide de migration et correction RouterOS v7

> Date : Juillet 2025  
> Version cible : RouterOS 7.0 – 7.23.1  
> Application : Mikhmonv2 (PHP Hotspot Manager)

---

## 1. Problèmes identifiés

| Problème | Symptôme | Cause racine |
|---|---|---|
| Tickets n'expirent pas | Les users restent actifs après la durée définie | Les scripts `on-login` et `bgservice` utilisaient `[:pick $date 0 3]` / `[:pick $date 7 11]` qui supposaient le format **legacy v6** (`MMM/DD/YYYY`). En v7, le format est ISO (`YYYY-MM-DD`), donc l'année extraite était `23` et le mois `202`. |
| Dates perdues au reboot | L'expiration ne fonctionne plus après coupure électrique | L'expiration dépendait de l'`uptime` du user et de schedulers temporaires créés à la volée. Ces schedulers disparaissent au reboot. |
| Revenus incorrects | Total journalier/mensuel erroné ou vide | `$totalresume` et `$dataresume` non initialisés ; `resumereport.php` dépendait de `$_SESSION['dataresume']` qui est vide après un redémarrage Docker ou une nouvelle session PHP. |
| Perte des données Docker | Les ventes disparaissent après `docker-compose down` | Les données de vente étaient uniquement en session PHP et en scripts RouterOS. Aucune persistance locale. |

---

## 2. Architecture des scripts v7-safe

### 2.1 Principe général

L'expiration repose désormais sur une **date absolue** stockée dans le **commentaire** de chaque user Hotspot. Cette date est écrite au moment de la première connexion (`on-login`) et relue périodiquement par un **scheduler de monitoring** (`bgservice`).

```
User se connecte
    ↓
on-login (profil) crée un scheduler temporaire avec interval = validity
    ↓
Lit le next-run (date absolue d'expiration)
    ↓
Écrit "MMM/DD/YYYY HH:MM:SS" dans le commentaire du user
    ↓
Supprime le scheduler temporaire

(boucle toutes les 2 min via bgservice)
    ↓
Pour chaque user du profil :
    Lire le commentaire → extraire la date d'expiration
    Comparer avec la date/heure actuelle du routeur
    Si expiré → remove user + remove active session
```

### 2.2 Détection dynamique du format de date RouterOS

Les scripts embarqués dans le routeur (générés par PHP) contiennent désormais une branche conditionnelle :

```routeros
:if ([:pick $date 4] = "-") do={
    # Format ISO v7 : YYYY-MM-DD
    :set year [:pick $date 0 4]
    :set month [:pick $date 5 7]
    :set day  [:pick $date 8 10]
    :local mnum [:tonum $month]
    :set month [:pick $montharray ($mnum - 1)]
} else={
    # Format legacy v6 : MMM/DD/YYYY
    :set month [:pick $date 0 3]
    :set day  [:pick $date 4 6]
    :set year [:pick $date 7 11]
}
```

**Avantage** : le même profil fonctionne indépendamment de la version RouterOS installée.

### 2.3 Format du commentaire user

Après la première connexion, le commentaire du user contient :

```
up-123-01.15.25-MyComment MMM/DD/YYYY HH:MM:SS
```

- La partie avant l'espace est le commentaire original généré par Mikhmon.
- La partie après l'espace est la **date d'expiration absolue**.

Le `bgservice` utilise une expression régulière pour lire cette date, qu'elle soit au format `MMM/DD/YYYY HH:MM:SS` ou `YYYY-MM-DD HH:MM:SS`.

---

## 3. Fichiers modifiés / créés

### 3.1 `include/mikhmon_compat.php` (NOUVEAU)

Couche de compatibilité et de persistance. Fonctions principales :

| Fonction | Rôle |
|---|---|
| `mikhmon_detect_routeros_date_format($date)` | Détecte `iso` vs `legacy` |
| `mikhmon_routeros_date_to_timestamp($date, $time)` | Convertit une date RouterOS en timestamp PHP |
| `mikhmon_date_to_legacy($dateStr)` | Normalise ISO → `MMM/DD/YYYY` pour les clés de rapports |
| `mikhmon_save_sale_log($session, $scriptData)` | Sauvegarde une vente dans un fichier JSON local (`data/sales_<session>.json`) |
| `mikhmon_load_sale_log($session, $owner)` | Charge les ventes persistantes |
| `mikhmon_remove_sale_by_user($session, $username)` | Supprime les entrées d'un user supprimé |
| `mikhmon_parse_user_comment_dates($comment)` | Extrait la date d'expiration du commentaire user |
| `mikhmon_get_user_lifetime_dates($API, $username)` | Récupère les dates via l'API RouterOS |
| `mikhmon_routeros_onlogin_script(...)` | Génère le script `on-login` v7-safe complet |
| `mikhmon_routeros_bgservice_script(...)` | Génère le script `bgservice` v7-safe complet |

### 3.2 `hotspot/adduserprofile.php` et `hotspot/userprofilebyname.php`

Les blocs de construction manuelle des scripts `$onlogin` et `$bgservice` ont été remplacés par des appels aux fonctions `mikhmon_routeros_onlogin_script()` et `mikhmon_routeros_bgservice_script()`.

**Avant** : code PHP inline long et fragile avec `[:pick $date 7 11]`.  
**Après** : scripts centralisés, testés et auto-détectants du format de date.

### 3.3 `hotspot/userbyname.php`

Ajout de deux lignes dans la fiche user :

- **Activation** : date/heure de première connexion (lue dans le commentaire)
- **Expiration** : date/heure de fin calculée par le `on-login`

Ces dates sont extraites via `mikhmon_parse_user_comment_dates()`.

### 3.4 `report/selling.php`

- Initialisation stricte de `$totalresume = 0` et `$dataresume = ''` avant la boucle.
- Utilisation de `floatval()` sur les prix pour éviter les erreurs de type.
- Appel à `mikhmon_save_sale_log()` à la fin de la boucle pour persister localement les ventes affichées.

### 3.5 `report/resumereport.php`

Avant d'afficher le graphique mensuel, le fichier **recalcule** `dataresume` et `totalresume` en lisant directement les `/system/script` du routeur (filtrés par `owner=$idbl`). Le graphique fonctionne donc même si la session PHP est neuve ou si le container a été redémarré.

### 3.6 `report/livereport.php`

Initialisation des variables `$tHr`, `$tBl`, `$TotalRHr` à `0` pour éviter les warnings sur le dashboard lorsque aucune vente n'est enregistrée.

### 3.7 `docker-compose.yml`

Ajout d'un volume Docker nommé `mikhmon_data` monté sur `/var/www/data` pour les services `php_7_4` et `nginx`.

```yaml
volumes:
  - mikhmon_data:/var/www/data
```

Le répertoire `data/` contient les fichiers JSON de vente (`sales_<session>.json`) qui survivent à tout redémarrage de container.

### 3.8 `process/removehotspotuser.php`

Ajout d'un appel à `mikhmon_remove_sale_by_user($session, $name)` juste avant la suppression du user et de ses logs. Cela synchronise la suppression avec le fichier JSON local.

### 3.9 `process/repairprofiles.php` (NOUVEAU)

Script de réparation automatique. Il est appelé via l'URL `?repair-profiles=1`.

**Fonctionnement** :
1. Liste tous les profils Hotspot via `/ip/hotspot/user/profile/print`.
2. Pour chaque profil ayant un `on-login`, extrait les paramètres d'origine (`expmode`, `validity`, `price`, `lock`, etc.) depuis le `:put` existant.
3. Régénère un `on-login` v7-safe et un `bgservice` v7-safe via `mikhmon_compat.php`.
4. Met à jour le profil et le scheduler de monitoring associé (ou le recrée s'il est manquant).

---

## 4. Guide d'utilisation du bouton "Repair All"

### 4.1 Quand l'utiliser

- **Immédiatement après** avoir déployé ces corrections sur un routeur en production.
- **Après une mise à jour** de RouterOS v6 → v7.
- **Si des tickets existants** n'expirent toujours pas correctement.

### 4.2 Étapes

1. Connectez-vous à Mikhmonv2 et sélectionnez la session du routeur.
2. Allez dans **Hotspot → User Profiles**.
3. Cliquez sur le bouton **🔧 Repair All** (à côté du bouton **Add**).
4. Confirmez l'alerte : *"Repair all profiles with v7-safe scripts?"*
5. Attendez la confirmation : *"Repaired X / Y profiles"*.

### 4.3 Vérification

Après la réparation, vérifiez dans **Winbox / WebFig** :
- `IP → Hotspot → User Profiles` : le champ `On Login` doit contenir des `[:pick $date 4]` et `[:pick $date 5 7]` (détection ISO).
- `System → Scheduler` : chaque profil avec expiration doit avoir un scheduler nommé comme le profil, avec un `On Event` contenant `dateint` et `timeint`.

### 4.4 Test de non-régression

1. Créez un profil avec une validité de `5m` et mode `Remove`.
2. Générez un ticket via ce profil.
3. Connectez-vous avec ce ticket.
4. Vérifiez dans `IP → Hotspot → Users` que le commentaire contient une date (ex: `up-123-01.15.25- jan/15/2025 14:30:00`).
5. Attendez 5 minutes : le user doit disparaître automatiquement.
6. Redémarrez le routeur, recréez un ticket, reconnectez-vous : le comportement doit être identique.

---

## 5. Bonnes pratiques RouterOS v7 pour l'expiration Hotspot

1. **Ne jamais se fier à l'`uptime`** du user après un reboot. Utilisez toujours des dates absolues dans les commentaires.
2. **Utiliser un scheduler de monitoring** (`bgservice`) avec un intervalle court (2 min) comme filet de sécurité. Les schedulers temporaires créés par `on-login` peuvent être perdus si le routeur reboot dans les 5 secondes suivant la connexion.
3. **Stocker l'expiration dans le commentaire** du user : c'est le seul champ persistant, facilement lisible par les scripts embarqués et par l'API PHP.
4. **Normaliser les dates de vente** au format `MMM/DD/YYYY` dans les `/system/script` pour que les rapports PHP restent cohérents, quelle que soit la version RouterOS.
5. **Vérifier la date système** du routeur après un reboot (`/system clock print`) pour s'assurer que le NTP a resynchronisé l'horloge avant que les schedulers ne s'exécutent.
6. **Prévoir un volume persistant** en Docker (fichier `docker-compose.yml` modifié) pour conserver les logs de vente locaux entre les redémarrages de container.

---

## 6. Dépannage rapide

| Symptôme | Diagnostic | Action |
|---|---|---|
| Tickets expirés mais toujours visibles | Le `bgservice` ne s'exécute pas | Vérifier `System → Scheduler` ; cliquer **Repair All** |
| Date de vente `jan/15/2025` devient `15/15/2025` | Format de date v7 mal interprété | **Repair All** obligatoire |
| Graphique mensuel vide | `dataresume` perdu en session | Ouvrir `resumereport.php` → il recalcule depuis RouterOS |
| Ventes disparues après `docker-compose down` | Pas de volume persistant | Ajouter `mikhmon_data:/var/www/data` dans `docker-compose.yml` et redémarrer |
| User supprimé mais revenu toujours compté | Log de vente local non nettoyé | Vérifier que `removehotspotuser.php` appelle `mikhmon_remove_sale_by_user()` |

---

## 7. Références techniques

- Format date RouterOS v7 : `YYYY-MM-DD` (ISO 8601)
- Format date RouterOS v6 : `MMM/DD/YYYY` (ex: `jan/15/2025`)
- Limite de nom de scheduler : 63 caractères
- Volume Docker : `mikhmon_data` (nommé, persistant)
- Fichier de persistance des ventes : `data/sales_<session>.json`
