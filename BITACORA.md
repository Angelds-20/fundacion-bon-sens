# 📋 Bitácora de Cambios - Fundación Bon Sens

Este archivo contiene el historial detallado de cambios técnicos, auditorías de seguridad, configuraciones aplicadas e infraestructura. **Este archivo está configurado en `.dockerignore` para que no sea copiado al contenedor en producción.**

---

## 📅 Historial de Cambios Recientes

### 🔧 Restauración de Estilos CSS (CORS & SRI) - Jun 23, 2026
*   **Problema:** La web se visualizaba como texto plano sin ningún tipo de estilo (diseño roto).
*   **Causa:**
    1.  Se había agregado `crossorigin="anonymous"` a la etiqueta `<script>` de Tailwind. Sin embargo, el CDN de Tailwind (`cdn.tailwindcss.com`) redirige internamente a Vercel/Cloudflare y **no responde con cabeceras CORS (`Access-Control-Allow-Origin: *`)**. Esto causaba que los navegadores bloquearan la descarga del script.
    2.  El hash de integridad (SRI) de FontAwesome 6.5.2 estaba incorrecto, bloqueando la carga de la hoja de estilos de iconos.
*   **Soluciones:**
    1.  Se removió `crossorigin="anonymous"` de todos los scripts de Tailwind en todos los HTML del proyecto.
    2.  Se corrigió el hash de integridad de FontAwesome por su valor oficial: `sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==`.
    3.  Se expandió la directiva de Content-Security-Policy (CSP) en `.htaccess` para permitir workers y scripts dinámicos con esquemas `blob:` en `worker-src` y `child-src`.

### 🛡️ Endurecimiento de Seguridad (Auditoría DAST: Nuclei & ZAP) - Jun 22, 2026
*   **Acciones:**
    1.  **Forzado de HTTPS (HSTS):** Se implementó la cabecera `Strict-Transport-Security` por un año para mitigar ataques de intermediario (Downgrade).
    2.  **MIME-Sniffing:** Se añadió la cabecera `X-Content-Type-Options "nosniff"`.
    3.  **Content-Security-Policy (CSP):** Se implementó una política restrictiva pero compatible con CDNs para mitigar Cross-Site Scripting (XSS).
        *   Permite scripts desde `'self'`, `'unsafe-inline'`, `'unsafe-eval'` (requerido por el JIT de Tailwind CDN), y `https://cdn.tailwindcss.com`.
        *   Permite estilos desde `'self'`, `'unsafe-inline'`, Google Fonts y cdnjs.
        *   Permite imágenes desde `'self'`, `data:`, `https://images.unsplash.com`, `https://plus.unsplash.com` y `https://picsum.photos`.
        *   Permite iframes (`frame-src` / `child-src`) de Google Maps (`https://www.google.com` y `https://maps.google.com`).
    4.  **Mitigación de Soft 404:** Se actualizó `router.php` para retornar un código de estado `404 Not Found` real en vez de redirigir a la Home (200 OK) cuando un recurso estático o ruta no existe.

### 🚪 Bloqueo de Rutas Sensibles y Defensa en Profundidad - Jun 22, 2026
*   **Acciones:**
    1.  **Bloqueo en Producción (`.htaccess`):** Configuración de reglas basadas en `THE_REQUEST` para rechazar solicitudes HTTP directas a `/backend/` e infraestructura (`Dockerfile`, `docker-compose.yml`, `fly.toml`, `.env`, `.git/`, scripts `.sh`, etc.) con un código de respuesta `403 Forbidden`.
    2.  **Bloqueo en Desarrollo Local (`router.php`):** Sincronización de las mismas reglas de bloqueo en el enrutador local de PHP.
    3.  **Seguridad en Script de Sembrado (`seed.php`):** Restricción estricta mediante `php_sapi_name()` para asegurar que solo se pueda ejecutar desde la terminal (CLI) y nunca a través de un navegador web.

### 🏠 Simplificación de la Home (`index.html`) - Jun 22, 2026
*   **Problema:** La Home estaba duplicando secciones del interior del sitio web.
*   **Solución:** Se transformó en una landing page limpia que consta únicamente del Hero de bienvenida, el contador de impacto y la sección de últimas noticias dinámicas.

---

## ⚙️ Arquitectura & Decisiones de Diseño

### ¿Por qué se utiliza Tailwind Play CDN?
*   **Contexto:** El proyecto está desarrollado en PHP puro y HTML estático estructurado de manera simple. No hay dependencias de Node.js (`package.json`) ni procesos de compilación (bundlers como Vite o Webpack).
*   **Ventaja:** Permite modificar archivos `.html` y reflejar los estilos instantáneamente tanto en local como en producción sin la complejidad de instalar y empaquetar Node.js en la máquina del desarrollador o dentro del contenedor Docker (`php:8.2-apache`).
*   **Compromiso de Seguridad:** Al usar el CDN, es mandatorio permitir `'unsafe-inline'` y `'unsafe-eval'` en el CSP ya que Tailwind Play escanea el DOM e inyecta dinámicamente hojas de estilo en tiempo de ejecución.

---

## 📊 Estado de Recursos (Supabase DB) - Jun 22, 2026
*   **CPU:** Idle/Reposo constante (uso de ~0.36%).
*   **Memoria (RAM):** ~408 MB consumidos (límite de 1 GB en plan gratuito). Muy holgado.
*   **Espacio en disco:** ~20 MB (0.02 GB) utilizados de 2 GB disponibles (menos del 1%).
*   **Caché:** Alto rendimiento de lectura gracias a que la base de datos se ejecuta casi completamente desde la caché de memoria sin requerir IOPS físicas en disco.
