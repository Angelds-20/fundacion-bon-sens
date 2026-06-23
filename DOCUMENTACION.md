# Documentación del Proyecto: Fundación Bon Sens

Bienvenido a la documentación oficial del desarrollo de la plataforma web de la **Fundación Bon Sens**, una organización solidaria con sede en Talca, Chile.

Esta plataforma consta de un sitio público informativo, un portal de donaciones en línea integrado con **Transbank Webpay Plus** (Banco Estado/Redcompra) y un panel de administración cerrado para gestionar el contenido y los registros de la fundación.

---

## Stack Tecnológico

- **Frontend**: HTML5, Vanilla JavaScript, CSS3 (con soporte auxiliar de Tailwind CSS para componentes dinámicos y responsive) y Font Awesome 6 para iconos.
- **Backend**: PHP 8.x (arquitectura Front Controller sin dependencias pesadas ni frameworks, facilitando el despliegue rápido en hosting tradicionales).
- **Base de Datos**: SQLite 3 (a través de PHP PDO), lo que elimina la necesidad de configurar servidores de bases de datos pesados para entornos pequeños y locales.
- **Seguridad**: Autenticación mediante tokens de sesión efímeros en base de datos, encriptación Bcrypt para credenciales, protección XSS y Rate Limiting básico contra abuso de peticiones.

---

## Estructura del Proyecto

```text
web/
├── index.html               # Página de inicio (Pública)
├── nosotros.html            # Quiénes Somos (Pública)
├── que-hacemos.html         # Programas de ayuda (Pública)
├── noticias.html            # Catálogo dinámico de noticias (Pública)
├── noticia.html             # Plantilla de detalle de noticia por URL limpia (Pública)
├── como-ayudar.html         # Información de donaciones y voluntariado (Pública)
├── contacto.html            # Formulario de contacto y mapa (Pública)
├── style.css                # Estilos personalizados generales del sitio
├── main.js                  # Frontend JS (Newsletter, Contacto, Donaciones, Menú Móvil)
├── logo2.PNG                # Logotipo oficial de la Fundación
├── router.php               # Router para servidor local de desarrollo PHP
├── .htaccess                # Configuración de URLs limpias y seguridad para Apache
├── start.sh                 # Script Bash de inicio automático y siembra de datos
│
├── data/
│   └── bonsens.db           # Base de datos SQLite auto-generada
│
├── backend/
│   ├── config.php           # Configuración del sistema, PDO y helpers globales
│   ├── api.php              # Enrutador API y controladores REST
│   └── seed.php             # Sembrador de datos de prueba (noticias y admin)
│
└── admin/
    ├── index.html           # SPA del Panel de Administración (Vista)
    └── js/
        └── admin.js         # Lógica del Panel (Dashboard, CRUD, Mensajes)
```

---

## Esquema de Base de Datos (SQLite)

El schema de datos se define e inicializa de forma automática en [backend/config.php](file:///home/angel/Descargas/web/backend/config.php#L49-L124):

### 1. `admin_users` (Administradores)
- `id` (INTEGER, PK, AutoIncrement)
- `username` (TEXT, Único)
- `password_hash` (TEXT) - Encriptada mediante BCRYPT
- `created_at` (DATETIME, Default: Now)

### 2. `admin_sessions` (Sesiones activas de admin)
- `id` (INTEGER, PK, AutoIncrement)
- `token` (TEXT, Único) - Token Bearer de seguridad
- `username` (TEXT)
- `expires_at` (DATETIME)
- `created_at` (DATETIME, Default: Now)

### 3. `contact_messages` (Mensajes de Contacto)
- `id` (INTEGER, PK, AutoIncrement)
- `name` (TEXT)
- `email` (TEXT)
- `phone` (TEXT)
- `subject` (TEXT)
- `message` (TEXT)
- `is_read` (INTEGER, Default: 0) - `0` para no leído, `1` para leído
- `created_at` (DATETIME, Default: Now)

### 4. `subscribers` (Suscripciones al Newsletter)
- `id` (INTEGER, PK, AutoIncrement)
- `email` (TEXT, Único)
- `name` (TEXT)
- `is_active` (INTEGER, Default: 1)
- `created_at` (DATETIME, Default: Now)

### 5. `news` (Noticias y Actividades)
- `id` (INTEGER, PK, AutoIncrement)
- `title` (TEXT)
- `slug` (TEXT, Único) - URL amigable
- `excerpt` (TEXT) - Breve resumen para el catálogo
- `content` (TEXT) - Contenido completo en HTML
- `image_url` (TEXT) - Enlace a imagen de cabecera
- `category` (TEXT, Default: general) - Categorías: general, operativos, campañas, hospitalario
- `is_published` (INTEGER, Default: 1)
- `published_at` (DATETIME, Default: Now)
- `created_at` (DATETIME, Default: Now)

### 6. `donations` (Registro de Donaciones)
- `id` (INTEGER, PK, AutoIncrement)
- `donor_name` (TEXT)
- `donor_email` (TEXT)
- `amount` (REAL)
- `currency` (TEXT, Default: CLP)
- `payment_method` (TEXT, Default: webpay)
- `status` (TEXT, Default: pending) - Estados: pending, completed, failed
- `transaction_id` (TEXT) - Código de autorización retornado por Transbank
- `message` (TEXT)
- `created_at` (DATETIME, Default: Now)

---

## Rutas de la API (Endpoints REST)

Todas las rutas inician con el prefijo `/api` y son manejadas por el Front Controller [backend/api.php](file:///home/angel/Descargas/web/backend/api.php).

| Método | Endpoint | Autenticación | Descripción |
| :--- | :--- | :--- | :--- |
| **GET** | `/health` | No | Retorna estado del servidor y versión de PHP |
| **GET** | `/stats` | No | Estadísticas básicas públicas |
| **POST** | `/admin/login` | No | Login administrador. Retorna token Bearer |
| **GET** | `/admin/me` | **Sí** | Valida el token y retorna el perfil de sesión |
| **GET** | `/admin/dashboard` | **Sí** | Estadísticas del dashboard e históricos |
| **POST** | `/contact` | No | Envía formulario de contacto público |
| **GET** | `/contact` | **Sí** | Lista mensajes recibidos (con paginación) |
| **PATCH** | `/contact/{id}/read` | **Sí** | Marca un mensaje de contacto como leído |
| **DELETE**| `/contact/{id}` | **Sí** | Elimina un mensaje de contacto |
| **GET** | `/news` | No | Lista noticias publicadas (paginación) |
| **GET** | `/news/{slug_o_id}` | No | Detalle de noticia por slug (público) o ID (admin) |
| **POST** | `/news` | **Sí** | Crea una nueva noticia |
| **PUT** | `/news/{id}` | **Sí** | Modifica una noticia existente |
| **DELETE**| `/news/{id}` | **Sí** | Elimina una noticia |
| **POST** | `/subscribe` | No | Suscripción rápida de newsletter |
| **GET** | `/subscribe` | **Sí** | Listado de suscriptores para el administrador |
| **DELETE**| `/subscribe/{id}` | **Sí** | Elimina un suscriptor de la base de datos |
| **POST** | `/donations` | No | Registra una intención de donación |
| **GET** | `/donations` | **Sí** | Historial de donaciones y monto recaudado |
| **GET** | `/donations/pay/{id}` | No | Inicializa y redirige hacia Transbank Webpay |
| **GET** | `/donations/confirm` | No | Callback final de Transbank (Webhook / Confirmación) |

---

## Integración de Pagos (Banco Estado / Webpay Plus)

El sistema de donaciones se integra con el API REST oficial de **Transbank Webpay Plus** (en su ambiente de pruebas/integración):

1. **Flujo de Pago**:
   - El usuario abre el modal de donación, ingresa su nombre, correo, mensaje y un monto (CLP) y envía el formulario.
   - El frontend ejecuta un `POST /api/donations` y recibe un `payment_url` apuntando a `/api/donations/pay/{id}`.
   - Al navegar a dicha URL, el servidor ejecuta una solicitud `cURL` (método `createWebpayTransaction`) con la API Key pública del comercio de integración.
   - Transbank devuelve un token único y una dirección de redirección. La página genera un formulario POST oculto y redirige al usuario a la pasarela bancaria oficial.
   - El usuario realiza el pago simulado (donde puede seleccionar **Banco Estado / PagoRUT / CuentaRut** o cualquier tarjeta de crédito/débito chilena).
   - Transbank procesa la transacción y devuelve al usuario al endpoint de retorno de la fundación: `/api/donations/confirm?token_ws=XXXX`.

2. **Confirmación**:
   - En `/api/donations/confirm`, el servidor consulta a Transbank (`confirmWebpayTransaction`) usando el token para confirmar si la transacción fue exitosa.
   - Si la respuesta es `AUTHORIZED`, la transacción pasa a estado `completed`, se registra el código de autorización en la base de datos y se muestra un comprobante de pago limpio y responsivo. En caso contrario, se notifica el rechazo.

3. **Simulador Offline**:
   - Si la máquina donde se ejecuta el servidor no tiene conexión a internet o los servidores de Transbank no responden, el controlador detecta el fallo en cURL y carga de forma transparente un **portal de simulación local**.
   - Este portal simula la interfaz permitiendo al desarrollador aprobar o rechazar el flujo manualmente con un solo clic, garantizando que el sistema sea testeable en cualquier condición.

---

## Enrutamiento de URLs Limpias (SEO)

Para maximizar la optimización en motores de búsqueda (SEO) y presentar un aspecto profesional, se ha eliminado la extensión `.html` de la barra de direcciones:

### Apache (`.htaccess`)
Implementa redirecciones internas transparentes que sirven los archivos HTML estáticos de forma interna:
- `/nosotros` -> `nosotros.html`
- `/que-hacemos` -> `que-hacemos.html`
- `/noticias` -> `noticias.html`
- `/noticias/{slug}` -> `noticia.html` (pasando el control a JS para cargar la noticia por slug)
- `/contacto` -> `contacto.html`
- `/como-ayudar` -> `como-ayudar.html`

### Servidor Local PHP (`router.php`)
Simula exactamente el mismo comportamiento de Apache en el entorno de desarrollo local (`php -S localhost:3000 router.php`).

---

## Panel de Administración (`/admin/`)

Es una Single Page Application (SPA) responsiva y estilizada que permite la gestión administrativa del sitio de forma segura:

- **Login**: Autenticado mediante tokens efímeros. Usuario por defecto: `admin` / Contraseña: `admin123`. (Se puede cambiar mediante variables de entorno `ADMIN_USERNAME` y `ADMIN_PASSWORD`).
- **Dashboard**: Vista general con estadísticas de mensajes no leídos, total de suscriptores, donaciones totales completadas, recaudación acumulada y lista de mensajes más recientes.
- **Mensajes**: Bandeja de entrada para leer, marcar como leído o eliminar los mensajes de contacto recibidos en el sitio público.
- **Noticias**: CRUD completo (Creación, Lectura, Modificación y Eliminación) de entradas de blog. Genera automáticamente los slugs basados en el título, permitiendo redactar en formato HTML enriquecido y asignar fotos de cabecera.
- **Suscriptores**: Lista de correos registrados en el newsletter con opción de filtrado y eliminación.
- **Donaciones**: Bitácora histórica con nombres de donantes, montos pagados en CLP, métodos de pago, estado de transacción y marcas de tiempo.

---

## Configuración y Puesta en Marcha

### Requisitos Previos
- PHP 8.0 o superior con las extensiones `pdo`, `pdo_sqlite`, `sqlite3` y `curl` habilitadas.

### Inicialización Local
El proyecto cuenta con un script de automatización [start.sh](file:///home/angel/Descargas/web/start.sh). Ejecuta los siguientes comandos en tu terminal dentro del directorio del proyecto:

```bash
# 1. Ejecutar la siembra inicial de la base de datos (noticias de prueba y usuario administrador)
bash start.sh --seed

# 2. Iniciar el servidor local en posteriores ocasiones
bash start.sh
```

El servidor web estará disponible en [http://localhost:3000](http://localhost:3000) y el panel administrativo en [http://localhost:3000/admin/](http://localhost:3000/admin/).

---

## Paso a Producción (Checklist)

Antes de lanzar el sitio web públicamente en un hosting real, es necesario completar las siguientes configuraciones de seguridad e integración:

1. **Configurar la URL Base**:
   - En [backend/config.php](file:///home/angel/Descargas/web/backend/config.php#L15), cambia `define('BASE_URL', 'http://localhost:3000');` por tu dominio de producción (ej. `https://www.fundacionbonsens.cl`).
2. **Definir Credenciales Seguras de Admin**:
   - Configura las variables de entorno `ADMIN_USERNAME`, `ADMIN_PASSWORD` y `SESSION_SECRET` en el hosting. Si tu hosting no permite variables de entorno fácilmente, modifícalas directamente en las constantes por defecto en [backend/config.php](file:///home/angel/Descargas/web/backend/config.php#L18-L20).
3. **Credenciales Reales de Transbank (Webpay)**:
   - Modifica las variables `$commerceCode` y `$apiKeySecret` en las funciones `createWebpayTransaction` y `confirmWebpayTransaction` de [backend/api.php](file:///home/angel/Descargas/web/backend/api.php).
   - Reemplaza la URL de integración (`https://webpay3gint.transbank.cl/...`) por la URL de producción correspondiente (`https://webpay3g.transbank.cl/...`).
4. **Habilitar Permisos de Escritura**:
   - Asegúrate de que el directorio `data/` y el archivo `bonsens.db` tengan permisos de escritura (chmod `775` o `777` dependiendo de la política del servidor web) para permitir que PHP registre mensajes de contacto y donaciones.

---

## Costos de Operación y Lanzamiento (Chile)

El presupuesto de mantenimiento y operación para la plataforma en Chile se desglosa a continuación:

### 1. Costos Fijos Iniciales (Anuales)
- **Dominio `.cl` (NIC Chile)**: **$10.990 CLP al año**. Se registra directamente en [nic.cl](https://www.nic.cl).
- **Web Hosting**: Desde **$2.500 a $5.000 CLP mensuales** (aprox. **$30.000 a $60.000 CLP anuales**). Cualquier plan básico Linux con soporte PHP 8.x es suficiente.
- **Certificado SSL (Seguridad HTTPS)**: **$0 CLP**. Incluido gratis en la mayoría de hostings de mercado (Let's Encrypt).

> **Inversión Fija Anual Estimada**: **$41.000 a $71.000 CLP**.

### 2. Comisiones por Transacción de Pasarela de Pagos (Sin costo mensual fijo)
- **Opción Directa (Transbank Webpay Plus)**:
  - *Costo fijo mensual:* $0 CLP.
  - *Comisión Débito/CuentaRUT:* ~1,49% + IVA por transacción.
  - *Comisión Crédito:* ~2,39% + IVA por transacción.
- **Opción Rápida Integradores (Flow.cl / PagoFácil)**:
  - *Costo fijo mensual:* $0 CLP.
  - *Comisión General:* ~2,89% + IVA por transacción. (Activación inmediata sin trámites bancarios complejos).
