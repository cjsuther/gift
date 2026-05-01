# Sistema de Gestión de Giftcards — Especificación Técnica

**Stack:** PHP 8.2+ · MySQL 8.0+ · Slim Framework 4 · Apache/Nginx · Composer
**Tipo:** Aplicación web multi-tenant con generación y canje de giftcards vía QR
**Idioma:** Español (rioplatense)

---

## 1. Resumen Ejecutivo

Plataforma SaaS donde múltiples **Establecimientos** gestionan **Giftcards** que entregan a sus clientes. Cada giftcard se materializa en un **código QR único** que el cliente presenta al establecimiento para canjear. El sistema lleva el control de qué giftcards están emitidas, vigentes, canjeadas, vencidas o canceladas.

### Actores y permisos

| Rol | Permisos |
|---|---|
| **Super Admin** | Alta/baja de Establecimientos. Creación del usuario administrador inicial de cada establecimiento. Vista global de la plataforma. |
| **Establishment Admin** | Crea, edita y elimina giftcards de su establecimiento. Gestiona los usuarios de su establecimiento (alta/baja de `establishment_user`). Ve todo el panel y los reportes. También puede canjear. |
| **Establishment User** | **Solo puede escanear QRs y marcar giftcards como canjeadas.** Ve listado de giftcards en modo lectura, sin acciones de creación, edición ni eliminación. |
| **Cliente final** | No tiene cuenta. Recibe el QR (impreso, por WhatsApp, email, etc.) y lo presenta para canjear. |

### Matriz de permisos detallada

| Acción | Super Admin | Estab. Admin | Estab. User |
|---|:-:|:-:|:-:|
| CRUD Establecimientos | ✅ | ❌ | ❌ |
| Crear `establishment_admin` | ✅ | ❌ | ❌ |
| Crear `establishment_user` | ✅ | ✅ (solo del propio est.) | ❌ |
| Ver listado de giftcards del establecimiento | ❌ | ✅ | ✅ (read-only) |
| Crear giftcard | ❌ | ✅ | ❌ |
| Editar giftcard | ❌ | ✅ | ❌ |
| Cancelar/eliminar giftcard | ❌ | ✅ | ❌ |
| Descargar QR / tarjeta imprimible | ❌ | ✅ | ❌ |
| Escanear QR y canjear | ❌ | ✅ | ✅ |
| Ver dashboard / reportes | ❌ | ✅ | ❌ |

> **Nota:** El `super_admin` no opera giftcards. Si necesita ayudar a un establecimiento, debe loguearse con un usuario del establecimiento (no hay impersonation en v1).

---

## 2. Stack Tecnológico

### Backend
- **PHP 8.2+** con tipado estricto (`declare(strict_types=1)`)
- **Composer** para dependencias
- **Slim Framework 4** + Slim PSR-7
- **PDO** para acceso a MySQL (prepared statements obligatorios)
- **Firebase JWT** (`firebase/php-jwt`) para autenticación stateless
- **endroid/qr-code** para generación de QR (PNG)
- **mPDF** para generación de tarjetas imprimibles (PDF estilizado vía HTML/CSS)
- **Intervention/Image** para procesamiento de imágenes subidas
- **PHPMailer** para notificaciones (fase opcional)
- **vlucas/phpdotenv** para variables de entorno

### Frontend
- **HTML + Tailwind CSS** (build local con CLI o CDN en desarrollo)
- **Alpine.js** para interactividad ligera
- **html5-qrcode** para escanear QR desde la cámara del navegador

### Base de datos
- **MySQL 8.0+** con `utf8mb4_unicode_ci`

---

## 3. Modelo de Datos (MySQL)

```sql
-- Establecimientos
CREATE TABLE establishments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#111827',  -- color de marca para la tarjeta imprimible
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios (super admin + usuarios de establecimientos)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id INT UNSIGNED DEFAULT NULL, -- NULL para super_admin
    role ENUM('super_admin','establishment_admin','establishment_user') NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_establishment (establishment_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giftcards
CREATE TABLE giftcards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    redeemed_by_user_id INT UNSIGNED DEFAULT NULL,
    token CHAR(32) NOT NULL UNIQUE,           -- token aleatorio para el QR
    title VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('active','redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
    expires_at DATE DEFAULT NULL,             -- opcional
    redeemed_at TIMESTAMP NULL DEFAULT NULL,
    recipient_name VARCHAR(150) DEFAULT NULL,
    recipient_contact VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (redeemed_by_user_id) REFERENCES users(id),
    INDEX idx_establishment_status (establishment_id, status),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de canjes (auditoría)
CREATE TABLE giftcard_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    giftcard_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    action ENUM('created','viewed','redeemed','cancelled','edited') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (giftcard_id) REFERENCES giftcards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_giftcard (giftcard_id),
    INDEX idx_action_date (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Notas del modelo

- El **token** de la giftcard es lo que viaja en el QR. Generarlo con `bin2hex(random_bytes(16))` (32 caracteres hex). Nunca exponer el `id` numérico en el QR.
- Agregué `primary_color` en `establishments` para personalizar la tarjeta imprimible.
- Agregué índice por `expires_at` para el cron que pasa giftcards a `expired`.

---

## 4. Estructura de Carpetas

```
giftcards/
├── public/
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   └── uploads/
│       ├── giftcards/
│       └── establishments/
├── src/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── EstablishmentController.php
│   │   ├── UserController.php
│   │   ├── GiftcardController.php
│   │   ├── RedemptionController.php
│   │   └── DashboardController.php
│   ├── Models/
│   │   ├── Establishment.php
│   │   ├── User.php
│   │   ├── Giftcard.php
│   │   └── GiftcardLog.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   └── TenantMiddleware.php
│   ├── Services/
│   │   ├── QrService.php             # Genera PNG del QR
│   │   ├── PrintableCardService.php  # Genera PDF de tarjeta imprimible (mPDF)
│   │   ├── ImageService.php
│   │   └── TokenService.php
│   ├── Database/
│   │   └── Connection.php
│   └── Helpers/
│       ├── Validator.php
│       └── Response.php
├── views/
│   ├── layouts/
│   ├── auth/
│   ├── admin/
│   ├── establishment/
│   ├── public/
│   └── pdf/
│       └── printable-card.php        # Template HTML del PDF imprimible
├── migrations/
│   ├── 001_create_establishments.sql
│   ├── 002_create_users.sql
│   ├── 003_create_giftcards.sql
│   └── 004_create_giftcard_logs.sql
├── config/
│   ├── app.php
│   └── database.php
├── .env.example
├── composer.json
└── README.md
```

---

## 5. Rutas y Endpoints

> Cada endpoint indica el rol mínimo requerido.

### Autenticación
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/auth/login` | público | Login (email + password). Devuelve JWT. |
| POST | `/api/auth/logout` | logueado | Invalida sesión. |
| GET | `/api/auth/me` | logueado | Datos del usuario actual. |

### Super Admin
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/admin/establishments` | `super_admin` | Listar establecimientos. |
| POST | `/api/admin/establishments` | `super_admin` | Crear establecimiento + su `establishment_admin` inicial. |
| PUT | `/api/admin/establishments/{id}` | `super_admin` | Editar. |
| DELETE | `/api/admin/establishments/{id}` | `super_admin` | Desactivar. |

### Gestión de usuarios del establecimiento
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/users` | `establishment_admin` | Listar usuarios del propio establecimiento. |
| POST | `/api/users` | `establishment_admin` | Crear `establishment_user` para el propio establecimiento. |
| PUT | `/api/users/{id}` | `establishment_admin` | Editar usuario del propio establecimiento. |
| DELETE | `/api/users/{id}` | `establishment_admin` | Desactivar. |

### Giftcards
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/giftcards` | `establishment_admin` o `establishment_user` | Listado del establecimiento. El user lo ve en modo lectura. |
| GET | `/api/giftcards/{id}` | ambos | Detalle. |
| POST | `/api/giftcards` | **solo `establishment_admin`** | Crear giftcard. |
| PUT | `/api/giftcards/{id}` | **solo `establishment_admin`** | Editar (sólo si `status=active`). |
| DELETE | `/api/giftcards/{id}` | **solo `establishment_admin`** | Cancelar. |
| GET | `/api/giftcards/{id}/qr` | **solo `establishment_admin`** | Descargar PNG del QR. |
| GET | `/api/giftcards/{id}/printable` | **solo `establishment_admin`** | Descargar PDF de la tarjeta imprimible. |
| GET | `/api/dashboard/stats` | `establishment_admin` | KPIs del establecimiento. |

### Canje
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/redeem/{token}` | logueado del establecimiento | Vista web con datos de la giftcard y botón de canje. |
| POST | `/api/redeem/{token}` | `establishment_admin` o `establishment_user` (del mismo est.) | Marca como canjeada. |

---

## 6. Flujos Principales

### 6.1 Alta de establecimiento (Super Admin)
1. Super admin entra a `/admin/establishments/new`.
2. Completa: nombre, slug, contacto, logo, color primario + datos del primer `establishment_admin` (email, nombre, password temporal).
3. Sistema crea `establishment` + `user` con rol `establishment_admin`.

### 6.2 Alta de Establishment User (Establishment Admin)
1. El admin del establecimiento entra a `/users/new`.
2. Carga nombre, email, password.
3. Sistema crea `user` con rol `establishment_user` y `establishment_id` igual al del admin que lo crea (forzado en backend, **no se confía en el form**).

### 6.3 Creación de giftcard (Establishment Admin)
1. Admin entra a `/giftcards/new`.
2. Completa: título, descripción, imagen (jpg/png/webp, máx 2 MB), destinatario opcional, fecha de vencimiento opcional.
3. Backend:
   - Genera `token = bin2hex(random_bytes(16))`.
   - Procesa imagen (resize a máx 1200px lado mayor, optimiza).
   - Guarda imagen en `public/uploads/giftcards/{establishment_id}/{token}.{ext}`.
   - Inserta giftcard con `status=active`.
   - Loguea acción `created`.
4. Redirige a detalle, donde puede:
   - Descargar **QR pelado** (PNG).
   - Descargar **tarjeta imprimible** (PDF). ← ver sección 7.
   - Compartir por WhatsApp con link `wa.me/?text=...`.

### 6.4 Canje de giftcard
1. Cliente llega con su QR (impreso o en celular).
2. Usuario del establecimiento (admin o user) abre `/scan`.
3. Escanea con `html5-qrcode` → navega a `/redeem/{token}`.
4. Backend valida:
   - Token existe.
   - Pertenece al `establishment_id` del usuario logueado. Si no, error 403.
   - `status === 'active'`.
   - No vencida (`expires_at IS NULL OR expires_at >= CURDATE()`).
5. Vista muestra: imagen, título, descripción, destinatario, fecha emisión, vencimiento.
6. Botón **"Marcar como canjeada"** con confirmación.
7. POST `/api/redeem/{token}` →
   - Update `status='redeemed'`, `redeemed_at=NOW()`, `redeemed_by_user_id`.
   - Loguea acción `redeemed`.
   - Devuelve confirmación visual con animación de éxito.
8. Si ya estaba canjeada: muestra "Esta giftcard ya fue canjeada el {fecha} por {usuario}." (sin permitir re-canje).

### 6.5 UX del Establishment User
- **Home del user al loguearse:** directo a `/scan` (no listado).
- Botón grande "Escanear QR" que activa la cámara con `html5-qrcode`.
- Acceso opcional a `/giftcards` en modo **read-only**, sólo para verificar manualmente si el QR no escanea (usando el `token_short` o búsqueda por destinatario). Sin botones de edición, eliminación ni descarga de QR/PDF.
- Sin acceso al dashboard ni a la gestión de usuarios.

### 6.6 Listado y filtros (Establishment Admin)
Vista `/giftcards`:
- Tabs: **Todas | Vigentes | Canjeadas | Vencidas | Canceladas**
- Búsqueda por título o destinatario.
- Orden por fecha de creación o de canje.
- Paginación (20 por página).
- Por fila: thumbnail, título, destinatario, status (badge), fecha, acciones (Ver, Editar, Descargar QR, Descargar PDF, Cancelar).

---

## 7. Diseño de la Tarjeta Imprimible (PDF)

Se genera con **mPDF** a partir de un template HTML/CSS (`views/pdf/printable-card.php`).

### Especificaciones

- **Formato:** A6 vertical (105 × 148 mm). Liviano, encaja en sobres comunes.
- **Variante secundaria:** A4 con 2 tarjetas por página (para ahorrar papel cuando se imprimen muchas). Endpoint: `?layout=a4-2up`.
- **Márgenes:** 8 mm.
- **Fuentes:** sans-serif del sistema (`DejaVu Sans` viene incluida en mPDF y soporta tildes/ñ).

### Layout vertical de la tarjeta (de arriba hacia abajo)

```
┌──────────────────────────────────┐
│ [logo_estab]   {NOMBRE_ESTAB}    │  ← header con color primario del estab.
├──────────────────────────────────┤
│                                  │
│      [imagen_giftcard]           │  ← 70 × 50 mm aprox, esquinas redondeadas
│                                  │
│      {TÍTULO_GIFTCARD}           │  ← 16pt bold
│      {DESCRIPCIÓN}               │  ← 10pt, máx 3 líneas
│                                  │
│         ████ ████ ████           │
│         ████  QR  ████           │  ← QR 35 × 35 mm centrado
│         ████ ████ ████           │
│         {token_corto}            │  ← primeros 8 chars del token, fallback manual
│                                  │
├──────────────────────────────────┤
│ Para canjear, presentá este QR   │
│ en {nombre_estab}                │  ← footer, 8pt
│ {dirección} · {teléfono}         │
│ Vence: {fecha} (si aplica)       │
└──────────────────────────────────┘
```

### Variables disponibles en el template

```php
$data = [
    'establishment' => [
        'name' => 'Rusvel',
        'logo_path' => '/uploads/establishments/1/logo.png',
        'primary_color' => '#E63946',
        'address' => 'Av. Saavedra 1234',
        'phone' => '11-5555-5555',
    ],
    'giftcard' => [
        'title' => 'Combo para 2 personas',
        'description' => 'Dos panchos especiales + papas + 2 bebidas',
        'image_path' => '/uploads/giftcards/1/abc123.jpg',
        'token' => 'a1b2c3d4e5f6...',
        'token_short' => 'A1B2C3D4',
        'expires_at' => '2026-12-31',
        'recipient_name' => 'Para: Juan Pérez',
    ],
    'qr_data_uri' => 'data:image/png;base64,...',
    'redeem_url' => 'https://app.com/redeem/a1b2c3d4e5f6...',
];
```

### Recomendaciones de implementación

- Generar el QR en PNG en memoria con `endroid/qr-code` y embeberlo como `data:image/png;base64,...` para no depender de rutas absolutas en el PDF.
- Usar el `primary_color` del establecimiento como acento (header, borde del QR, título). Si no hay color, fallback a `#111827`.
- El **token corto** (8 primeros caracteres en mayúsculas) sirve como fallback si el QR no escanea bien: el usuario del establecimiento puede tipearlo manualmente en `/redeem` (agregar input opcional en la vista de canje).

### Endpoint
```
GET /api/giftcards/{id}/printable?layout=a6
GET /api/giftcards/{id}/printable?layout=a4-2up
```

Devuelve `Content-Type: application/pdf` con `Content-Disposition: attachment; filename="giftcard-{token_short}.pdf"`.

---

## 8. Seguridad

- **Hash de passwords** con `password_hash($pwd, PASSWORD_BCRYPT)` y `password_verify`.
- **JWT** con expiración 2 hs. Secret en `.env`.
- **CSRF** en formularios web tradicionales (token por sesión).
- **Validación de uploads**: whitelist MIME (`image/jpeg`, `image/png`, `image/webp`), tamaño máx, renombrado del archivo, `php_flag engine off` en `.htaccess` del directorio `uploads/`.
- **Prepared statements** en TODAS las queries.
- **TenantMiddleware**: para todos los endpoints que tocan giftcards/usuarios, verifica que el `establishment_id` del recurso coincida con el del usuario logueado. Centralizado, sin excepciones.
- **RoleMiddleware**: valida el rol mínimo requerido por endpoint (ver tabla en sección 5).
- **Rate limit** en `/api/redeem/*` (30 req/min por IP) para evitar brute force de tokens.
- **HTTPS obligatorio** en producción (sin excepción para escaneo desde celular).
- **Logs de canje** en `giftcard_logs` con IP y user agent.
- **Validación de pertenencia al canjear**: aunque el QR sea válido, si el usuario logueado no es del mismo establecimiento → 403.

---

## 9. Configuración (.env)

```env
APP_ENV=local
APP_URL=http://localhost:8000
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=giftcards
DB_USER=root
DB_PASSWORD=

JWT_SECRET=cambiar-por-string-aleatorio-largo
JWT_TTL_HOURS=2

UPLOAD_MAX_MB=2
UPLOAD_PATH=public/uploads

# Super admin inicial (seeder)
SUPERADMIN_EMAIL=admin@app.com
SUPERADMIN_PASSWORD=cambiar-en-primer-login

# Opcional fase 2
MAIL_HOST=
MAIL_PORT=
MAIL_USER=
MAIL_PASS=
MAIL_FROM=no-reply@app.com
```

---

## 10. Dependencias (composer.json)

```json
{
  "require": {
    "php": ">=8.2",
    "slim/slim": "^4.0",
    "slim/psr7": "^1.6",
    "firebase/php-jwt": "^6.10",
    "endroid/qr-code": "^5.0",
    "mpdf/mpdf": "^8.2",
    "intervention/image": "^3.5",
    "vlucas/phpdotenv": "^5.6",
    "phpmailer/phpmailer": "^6.9"
  },
  "require-dev": {
    "phpunit/phpunit": "^10"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

---

## 11. Fases de Implementación (para Claude Code)

### Fase 1 — Esqueleto y autenticación
- [ ] Estructura de carpetas + `composer.json` + `.env.example`.
- [ ] Setup Slim + router + `Connection.php` (PDO singleton).
- [ ] Migrations: ejecutar los 4 SQL.
- [ ] Seeder: crear super admin desde `.env`.
- [ ] Endpoints `/auth/login`, `/auth/me`, `/auth/logout`.
- [ ] `AuthMiddleware` (valida JWT).
- [ ] `RoleMiddleware` (valida rol).
- [ ] `TenantMiddleware` (valida pertenencia al establecimiento).
- [ ] Vistas de login con Tailwind.

### Fase 2 — Super Admin
- [ ] CRUD de establecimientos (con upload de logo + color primario).
- [ ] Formulario "nuevo establecimiento" que crea también el primer `establishment_admin`.
- [ ] Listado con tabla + buscador.

### Fase 3 — Gestión de usuarios del establecimiento
- [ ] CRUD de `establishment_user` desde el panel del `establishment_admin`.
- [ ] Forzar `establishment_id` en backend al crear (nunca confiar en el form).
- [ ] Listado con buscador.

### Fase 4 — Giftcards (CRUD)
- [ ] `GiftcardController` completo, con permisos por rol.
- [ ] `TokenService` (generación de tokens únicos).
- [ ] `ImageService` (validación, resize, guardado).
- [ ] `QrService` (generación PNG).
- [ ] Vista listado con filtros por status.
- [ ] Vista detalle con thumbnail, datos y descarga del QR.
- [ ] Para `establishment_user`: listado **read-only** (sin botones de crear/editar/eliminar).

### Fase 5 — Tarjeta imprimible (PDF)
- [ ] `PrintableCardService` con mPDF.
- [ ] Template `views/pdf/printable-card.php` con layout A6.
- [ ] Variante A4 con 2 tarjetas por hoja.
- [ ] Endpoint `/api/giftcards/{id}/printable`.
- [ ] Botones de descarga en la vista de detalle.

### Fase 6 — Canje
- [ ] Vista `/scan` con `html5-qrcode` (cámara del navegador).
- [ ] Vista `/redeem/{token}` con validación completa.
- [ ] Endpoint POST `/api/redeem/{token}`.
- [ ] Logging en `giftcard_logs`.
- [ ] Estados visuales: vigente / canjeada / vencida / cancelada / no pertenece.
- [ ] Input manual del `token_short` como fallback si el QR no escanea.

### Fase 7 — Dashboard y reportes
- [ ] `/api/dashboard/stats` (solo `establishment_admin`): KPIs.
- [ ] Vista dashboard con cards (total emitidas, canjeadas mes actual, vigentes, % de canje).
- [ ] Export CSV/XLSX del listado de giftcards.

### Fase 8 (opcional) — Mejoras
- [ ] Cron diario que pasa a `expired` las giftcards con `expires_at < CURDATE()`.
- [ ] Notificaciones por WhatsApp/email al destinatario al crear giftcard.
- [ ] Tema dark mode.
- [ ] Multi-logo y plantillas alternativas de tarjeta.

---

**Listo para Claude Code.** Pasale este documento como contexto inicial y arrancá por la Fase 1.
