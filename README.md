# Fundación Bon Sens - Plataforma Web Solidaria

Plataforma web desarrollada a medida para la Fundación Bon Sens (Talca, Chile). Cuenta con un sitio público, panel de administración privado (SPA) para la gestión de contenido/mensajería y una integración con la pasarela de pagos Transbank Webpay Plus para donaciones.

El proyecto está estructurado para funcionar de manera híbrida: SQLite para desarrollo local y PostgreSQL para entornos de producción.

---

## Stack Tecnológico

* **Frontend:** HTML5, Vanilla JavaScript, CSS3 (Tailwind CSS y Font Awesome 6).
* **Backend:** PHP 8.x (arquitectura Front Controller sin dependencias de frameworks).
* **Bases de Datos:**
  * **Local:** SQLite 3 (a través de PHP PDO).
  * **Producción:** PostgreSQL (Supabase / Neon DB).
* **Infraestructura:** Docker & Docker Compose (Apache/PHP).

---

## Características

### Sitio Público
* **URLs Limpias:** Enrutamiento amigable mediante reglas de reescritura en Apache (`.htaccess`) para eliminar extensiones `.html`.
* **Noticias Dinámicas:** Carga e historial de actividades desde base de datos vía API.
* **Newsletter y Contacto:** Formularios validados con control básico de spam.
* **Donaciones Webpay:** Integración con la pasarela oficial (ambiente de integración) y simulador local en caso de desconexión.

### Panel de Administración
Acceso restringido mediante tokens Bearer efímeros en la ruta `/admin`.
* **Dashboard:** Estadísticas de mensajes no leídos, total de suscriptores y recaudación.
* **Mensajería:** Visualización y eliminación de mensajes de contacto.
* **Gestor de Noticias:** CRUD de entradas de blog con generación de slugs.
* **Bitácora Financiera:** Registro de donaciones, estados de transacción y montos.

---

## Estructura del Proyecto

```text
├── index.html               # Página de inicio
├── nosotros.html            # Quiénes Somos
├── que-hacemos.html         # Programas
├── noticias.html            # Catálogo de noticias
├── como-ayudar.html         # Portal de donaciones
├── contacto.html            # Contacto
├── Dockerfile               # Empaquetado de contenedor
├── docker-compose.yml       # Orquestación local
├── entrypoint.sh            # Inicialización de servicios
│
├── backend/
│   ├── config.php           # Configuración PDO y DDL dinámica
│   ├── api.php              # Front Controller de la API REST
│   └── seed.php             # Script de datos iniciales
│
└── admin/
    ├── index.html           # SPA Panel de Administración
    └── js/
        └── admin.js         # Lógica de la SPA
```

---

## ¿Por qué incluye Docker y Render en el repositorio?

Incluir la configuración de Docker y las instrucciones de despliegue en el portafolio demuestra habilidades en DevOps y despliegue continuo:

1. **Portabilidad:** Permite a cualquier desarrollador o reclutador ejecutar el proyecto en su máquina local con un solo comando, sin instalar PHP ni bases de datos.
2. **Entorno de producción real:** Valida que el código está listo para producción (contenedorizado) y es capaz de conectarse a bases de datos relacionales en la nube (PostgreSQL).

### Ejecución Local (Docker)

```bash
docker compose up --build
```
* **Sitio Web:** http://localhost:3000
* **Panel Admin:** http://localhost:3000/admin (Credenciales definidas en el archivo .env o variables de entorno)

### Despliegue en Producción (Render + Supabase)

1. Crea un proyecto Postgres en Supabase y copia la URI de conexión.
2. Crea un Web Service en Render seleccionando la opción **Docker**.
3. Configura las siguientes variables de entorno en Render:
   * `DATABASE_URL`: URI de Supabase.
   * `ADMIN_USERNAME`: Usuario administrador.
   * `ADMIN_PASSWORD`: Contraseña del panel.
   * `SESSION_SECRET`: Clave para firma de tokens.
   * `BASE_URL`: URL del hosting en Render.
