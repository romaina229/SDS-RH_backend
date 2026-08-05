# SDS-RH — Backend API

Backend Laravel 13 pour la gestion RH multi-tenant de SDS-RH.

## Stack

- PHP 8.3+
- Laravel 13
- Laravel Sanctum
- Spatie Laravel Permission
- MySQL recommandé
- API REST JSON

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Le seeder principal crée les rôles et permissions nécessaires. Les données de démonstration sont volontairement séparées :

```bash
php artisan db:seed --class=TenantSeeder
```

Pour un environnement de production, définissez impérativement `APP_KEY`, la base de données, `FRONTEND_URLS`, le mailer et les paramètres de stockage.

## API

Toutes les routes métier sont sous `/api` et utilisent des tokens Sanctum. Les utilisateurs standards sont automatiquement isolés par `tenant_id`. Un super-administrateur utilise les routes `/api/admin/*`.

## Réinitialisation de mot de passe

Les liens de réinitialisation sont générés vers le frontend via `FRONTEND_URL`.

## Documents

Les documents RH sont stockés sur le disque privé et servis uniquement via les routes API authentifiées.

## Paie

Le module calcule les cotisations CNSS configurées pour le Bénin. Le calcul de l'impôt sur les traitements et salaires doit être paramétré selon le régime fiscal applicable avant toute utilisation comme moteur de paie légal.

## Paiements

L'ancien module FedaPay incomplet a été retiré du runtime afin d'éviter une intégration partiellement fonctionnelle. Il pourra être réintroduit comme module séparé avec le SDK officiel lorsque le besoin de facturation sera défini.
