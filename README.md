# Sistema de Giftcards — Fase 1

Esqueleto, autenticación JWT y middlewares de seguridad (Auth + Role + Tenant).

## Requisitos
- PHP 8.2+ con extensiones `pdo`, `pdo_mysql`, `mbstring`, `json`
- MySQL 8.0+
- Composer

## Setup local

```bash
# 1. Clonar y entrar al proyecto
cd giftcards/

# 2. Copiar el .env y completar
cp .env.example .env
# Editar .env: DB_*, JWT_SECRET (mínimo 16 chars), SUPERADMIN_*

# 3. Instalar dependencias
composer install

# 4. Crear la base
mysql -u root -p -e "CREATE DATABASE giftcards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Correr migrations
composer migrate

# 6. Crear el super admin desde .env
composer seed

# 7. Levantar el server de desarrollo
php -S localhost:8000 -t public
```

Visitar:
- `http://localhost:8000/login` — vista de login
- `http://localhost:8000/api/health` — healthcheck público

## Tests

```bash
composer test
```

Cubren los tres middlewares críticos:
- **AuthMiddleware**: extracción de JWT (header y cookie), validación, lookup de usuario, rechazo de tokens expirados o con rol desincronizado.
- **RoleMiddleware**: whitelist explícita de roles (sin jerarquía implícita), rechazo defensivo si no hay user inyectado.
- **TenantMiddleware**: rechaza super_admin, exige `establishment_id`, expone el tenant como atributo del request, e incluye un helper `ownsResource()` para validar pertenencia de recursos cargados en controllers.

## Arquitectura de autenticación

Cadena por ruta:

```
AuthMiddleware  →  RoleMiddleware  →  TenantMiddleware  →  handler
   (JWT + DB)        (whitelist)          (tenant scope)
```

**Reglas no negociables:**
- Nunca leer `establishment_id` del body, query o ruta. Fuente de verdad: el request attribute inyectado por `TenantMiddleware`.
- Nunca confiar en jerarquías ("super_admin > admin > user"). Whitelist explícita en cada `RoleMiddleware`.
- Nunca pasar el `id` numérico de una giftcard en el QR. Solo el `token` (32 hex).

## Estructura

```
src/
├── Auth/            AuthenticatedUser, JwtService, UserProvider, PdoUserRepository
├── Controllers/     AuthController (login/me/logout)
├── Middleware/      AuthMiddleware, RoleMiddleware, TenantMiddleware
├── Helpers/         Response (JSON), Validator
├── Database/        Connection (PDO singleton)
└── app.php          Bootstrap de Slim + wiring

tests/
├── Middleware/      Tests de los 3 middlewares
└── Support/         Fixtures (TestRequest, PassthroughHandler, InMemoryUserProvider)
```

## Próximas fases
Ver `giftcards-spec.md` (Fase 2 en adelante).
