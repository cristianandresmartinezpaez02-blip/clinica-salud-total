# Clínica Salud Total

Sitio web y sistema de gestión para una **clínica privada**, desarrollado en **PHP + MySQL**: página pública, portal del paciente con citas en línea y paneles para recepción, médicos, enfermería, farmacia y administración.

> Proyecto académico. Los datos de contacto y el contenido institucional son de ejemplo.

## Cómo funciona la clínica en el sistema

En una clínica privada cada cita tiene un **turno reservado con un médico**. Las **urgencias no se agendan**: se atienden 24 horas por llegada directa y no desplazan la agenda.

```
Paciente solicita (en línea) ─▶ PENDIENTE ─▶ Recepción asigna médico y consultorio ─▶ CONFIRMADA
Recepción agenda (presencial / teléfono) ────────────────────────────────────────────▶ CONFIRMADA
CONFIRMADA ─▶ el paciente llega (recepción) ─▶ EN SALA ─▶ el médico registra la consulta ─▶ ATENDIDA
CONFIRMADA ─▶ NO ASISTIÓ  ·  PENDIENTE / CONFIRMADA ─▶ CANCELADA
```

| Rol | Qué hace |
|---|---|
| **Paciente** | Crea su cuenta (datos, correo, foto de perfil y foto del documento). Cuando el administrador la aprueba, solicita citas eligiendo especialidad, día y turno libre; ve sus citas, historia, fórmulas y facturas; puede cancelar sus citas y cambiar su contraseña. |
| **Recepción** | Dueña de la agenda: confirma las solicitudes en línea asignando médico y consultorio, agenda citas presenciales y telefónicas (busca al paciente o lo registra en el momento), marca llegadas, inasistencias y cancelaciones. |
| **Médico** | Ve solo su agenda y la cola de pacientes en sala; registra la consulta (diagnóstico, tratamiento y fórmula), que pasa a farmacia. |
| **Enfermería** | Consulta la agenda del día y los pacientes en sala; responde mensajes de contacto. |
| **Farmacia** | Despacha las fórmulas de las consultas y factura. |
| **Administrador** | Crea las cuentas del personal, aprueba o rechaza la identidad de los pacientes registrados en línea, supervisa la agenda y responde mensajes. |

### Reglas de la agenda
- Turnos de 30 minutos. Lunes a viernes 7:00 a.m. – 6:00 p.m., sábados 8:00 a.m. – 1:00 p.m., domingos sin consulta.
- Cada especialidad atiende un paciente por turno y tiene un cupo diario.
- Un paciente no puede tener dos citas a la misma hora ni más de 3 solicitudes pendientes.
- Se agenda con máximo 60 días de anticipación.

## Instalación (XAMPP)

1. Copie el proyecto en `C:\xampp\htdocs\proyecto_productivo`.
2. Inicie **Apache** y **MySQL** desde el panel de XAMPP.
3. En phpMyAdmin cree la base de datos `campaez_proyecto` (utf8mb4) e importe `base_datos/campaez_proyecto.sql`.
4. Copie `config/config.example.php` como `config/config.local.php` y ajuste sus datos (base de datos, correo, Twilio).
5. Abra <http://localhost/proyecto_productivo/>.

Si instala el proyecto en otra carpeta, cambie `app.base_url` en `config/config.local.php` y la ruta de `ErrorDocument` en `.htaccess`.

**¿Ya tenía una base de datos anterior?** El sistema agrega solo el rol Recepción y las columnas nuevas de la agenda. Como ahora se ingresa con **correo**, asigne un correo a cada usuario antiguo desde *Administrador → Usuarios → Editar*.

## Cuentas de demostración

Se ingresa con **correo y contraseña**.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@demo.local` | `Admin2026*` |
| Recepción | `recepcion@demo.local` | `Recepcion2026*` |
| Médico (Medicina General) | `medico@demo.local` | `Medico2026*` |
| Médico (Pediatría) | `pediatra@demo.local` | `Pediatra2026*` |
| Enfermería | `enfermero@demo.local` | `Enfermero2026*` |
| Farmacia | `farmacia@demo.local` | `Farmacia2026*` |
| Paciente | `paciente@demo.local` | `Paciente2026*` |

**Cambie estas contraseñas** si publica el sistema en un servidor real.

## Estructura

```
config/            Configuración (config.local.php no se versiona) y arranque común
includes/          Cabecera, pie, íconos y protección de formularios públicos
assets/            CSS, JS e imágenes del diseño (sitio, login, paneles, documentos)
index.php          Página de inicio
subpaginas/        Páginas públicas: servicios, especialidades, citas, pacientes, nosotros, contacto
log_in/            Ingreso, paneles por rol (modulos/) y servicios (conexion/)
  conexion/citas_servicio.php        Ciclo de vida de las citas
  conexion/disponibilidad_cita.php   Reglas de la agenda y turnos libres
  modulos/recepcion.php              Panel de recepción
base_datos/        Esquema SQL con datos de demostración
Imagenes/          Imágenes del sitio
```

## Seguridad

- Ingreso con correo y contraseña; contraseñas con `password_hash` (las antiguas en texto plano se convierten al iniciar sesión).
- Límite de 5 intentos fallidos de inicio de sesión.
- Consultas preparadas (`mysqli` + `bind_param`).
- Sesión con cookies `HttpOnly` y `SameSite=Strict`, regeneración de ID al iniciar sesión.
- Protección CSRF en el ingreso y en todos los formularios y acciones de los paneles.
- Formularios públicos protegidos contra spam (campo trampa, tiempo mínimo firmado y límite de envíos).
- El registro público solo crea pacientes y exige aprobación del administrador; el personal lo crea un administrador.
- Correos únicos en todo el sistema; el documento verificado no se puede cambiar desde el portal.
- Fotos de perfil y de documentos fuera del acceso público, visibles solo para administración y recepción.
- Cada médico ve solo sus citas y sus avisos; cada paciente solo sus citas.
- Credenciales fuera del código fuente (`config/config.local.php`). Zona horaria de Colombia en PHP y MySQL.

## Tecnologías

PHP 8, MySQL/MariaDB, HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons y Font Awesome Free.
