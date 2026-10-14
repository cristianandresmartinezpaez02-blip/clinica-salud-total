# Clínica Salud Total

Sitio web y sistema de gestión clínica desarrollado en **PHP + MySQL**: página pública de la clínica, agendamiento de citas en línea y paneles por rol (administrador, médico, enfermería, farmacia y paciente).

> Proyecto académico. Los datos de contacto y el contenido institucional son de ejemplo.

## Funcionalidades

**Sitio público**
- Inicio con accesos rápidos, servicios, información para pacientes, blog y mapa.
- Catálogo de servicios con buscador y solicitud de cita.
- Agendamiento de citas con especialidades y disponibilidad en tiempo real.
- Pacientes y visitantes, Investigaciones, Donaciones y Contáctenos.
- Transparencia con informes de gestión, actas y política de tratamiento de datos (Ley 1581 de 2012).
- Diseño adaptable a móvil con cabecera, pie y paleta de colores comunes a todo el sitio.
- Página 404 propia, `robots.txt` y `sitemap.xml`.

**Sistema interno (`log_in/`)**
- Inicio de sesión con verificación de identidad (tipo y número de documento).
- Registro de pacientes con foto del documento y selfie, aprobados por un administrador.
- Paneles por rol: administración de usuarios, citas, mensajes y donaciones; diagnósticos y fórmulas médicas; enfermería; farmacia y facturación; portal del paciente.
- Diseño unificado para los cinco paneles, con menú lateral adaptable a celular y tema claro u oscuro.
- Resumen del administrador con tareas pendientes (identidades por verificar, citas por aprobar, mensajes sin responder).
- Secciones *Mi perfil* (datos y contraseña) y *Configuración* (tema y animaciones) en los paneles del personal.
- Notificaciones en el panel, por correo (Gmail) y opcionalmente SMS/WhatsApp (Twilio).

## Instalación (XAMPP)

1. Copie el proyecto en `C:\xampp\htdocs\proyecto_productivo`.
2. Inicie **Apache** y **MySQL** desde el panel de XAMPP.
3. En phpMyAdmin cree la base de datos `campaez_proyecto` (utf8mb4) e importe `base_datos/campaez_proyecto.sql`.
4. Copie `config/config.example.php` como `config/config.local.php` y ajuste sus datos (base de datos, correo, Twilio).
5. Abra <http://localhost/proyecto_productivo/>.

Si instala el proyecto en otra carpeta, cambie `app.base_url` en `config/config.local.php` y la ruta de `ErrorDocument` en `.htaccess`.

## Usuarios de demostración

Para iniciar sesión se pide usuario, contraseña, módulo y documento (tipo **Cédula de ciudadanía**).

| Módulo | Usuario | Contraseña | Documento |
|---|---|---|---|
| Administrador | `admin` | `Admin2026*` | 1000000001 |
| Médico | `medico` | `Medico2026*` | 1000000002 |
| Enfermería | `enfermero` | `Enfermero2026*` | 1000000003 |
| Farmacia | `farmacia` | `Farmacia2026*` | 1000000004 |
| Paciente | `paciente` | `Paciente2026*` | 1000000005 |

**Cambie estas contraseñas** si publica el sistema en un servidor real.

## Estructura

```
config/            Configuración (config.local.php no se versiona) y arranque común
includes/          Cabecera, pie e íconos compartidos de las páginas públicas
assets/            CSS, JS e imágenes del diseño
index.php          Página de inicio
subpaginas/        Páginas públicas (servicios, citas, transparencia, etc.)
log_in/            Inicio de sesión, paneles por rol y servicios (conexion/)
base_datos/        Esquema SQL con datos de demostración
Imagenes/          Imágenes del sitio
```

## Seguridad

- Contraseñas con `password_hash` (las antiguas en texto plano se convierten automáticamente al iniciar sesión).
- Consultas preparadas (`mysqli` + `bind_param`).
- Sesión con cookies `HttpOnly` y `SameSite=Strict`, regeneración de ID al iniciar sesión.
- Límite de 5 intentos fallidos de inicio de sesión.
- Protección CSRF en el inicio de sesión y en todos los formularios y acciones de los paneles (token por sesión inyectado automáticamente).
- Formularios públicos protegidos contra spam: campo trampa, tiempo mínimo de llenado firmado y límite de envíos.
- Cada visitante solo ve la confirmación de la cita que él mismo creó.
- El registro público solo crea pacientes; el personal lo crea un administrador.
- Subidas de imágenes validadas por contenido, con nombre aleatorio y sin ejecución de scripts en `uploads/`.
- Las fotos de documentos de identidad solo las puede ver un administrador.
- Credenciales fuera del código fuente (`config/config.local.php`).
- Zona horaria de Colombia (`America/Bogota`) en PHP y MySQL.
- Cabeceras de seguridad y bloqueo de carpetas internas vía `.htaccess`.

## Tecnologías

PHP 8, MySQL/MariaDB, HTML5, CSS3, JavaScript, Bootstrap 5 e íconos de Bootstrap Icons y Font Awesome Free.
