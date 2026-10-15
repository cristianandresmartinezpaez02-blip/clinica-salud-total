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
| **Paciente** | Crea su cuenta (datos, correo, foto de perfil y foto del documento). Cuando el administrador la aprueba, ve **qué días hay cupo** y solicita citas eligiendo especialidad, día y turno libre; **acepta o rechaza los cambios de horario** que le propone Recepción o elige él mismo otro turno libre; ve sus citas, historia, fórmulas, órdenes e incapacidades; puede cancelar sus citas y cambiar su contraseña. |
| **Recepción** | Dueña de la agenda: confirma las solicitudes en línea asignando médico y consultorio, agenda citas presenciales y telefónicas (busca al paciente o lo registra en el momento), marca llegadas, inasistencias y cancelaciones. Cambia el día y la hora de las citas **proponiendo el nuevo horario al paciente** (o aplicándolo de inmediato si el paciente ya aceptó por teléfono) y atiende la bandeja **Por reprogramar**. |
| **Enfermería** | Hace el **triaje** de los pacientes en sala: signos vitales (presión, frecuencias, temperatura, saturación, peso, talla, IMC, glucometría, dolor) y antecedentes (alergias, enfermedades crónicas, medicamentos, cirugías, grupo sanguíneo). Valida rangos y alerta valores anormales. |
| **Médico** | Ve solo su agenda y la cola de pacientes en sala. Al atender recibe el triaje, las **alergias resaltadas**, los antecedentes y las consultas anteriores, y diligencia la **historia clínica estructurada** (ver abajo), que firma electrónicamente. Solicita **permisos** con mínimo 7 días de anticipación. |
| **Farmacia** | Despacha las fórmulas de las consultas y factura. |
| **Administrador (coordinación)** | Crea las cuentas del personal, aprueba o rechaza la identidad de los pacientes, **aprueba o rechaza los permisos médicos** y registra novedades sin preaviso (incapacidades, calamidades), supervisa la agenda, consulta **reportes** y la **auditoría** de accesos. |

### Historia clínica (registro de consulta externa)
Organizada como en una clínica (formato SOAP):
- **Identificación** tomada de la ficha del paciente (no se vuelve a digitar), tipo de consulta, causa externa y acompañante.
- **S – Anamnesis:** motivo de consulta, enfermedad actual, revisión por sistemas y antecedentes (actualizan la ficha).
- **O – Examen físico:** signos vitales del triaje (o los toma el médico si no hubo triaje) y examen por sistemas.
- **A – Análisis** y diagnósticos **CIE-10** (principal con tipo de diagnóstico y hasta 3 relacionados; lista de los más frecuentes o cualquier código válido).
- **P – Plan:** conducta, **fórmula médica estructurada** (medicamento, presentación, dosis, vía, frecuencia, días, cantidad), **órdenes** de laboratorio, imágenes, procedimientos e interconsultas, **incapacidad**, recomendaciones y signos de alarma, y control.
- **Firma electrónica** con nombre, especialidad y registro médico, y código de verificación. La consulta firmada no se modifica.
- Documentos imprimibles: historia clínica, fórmula médica, órdenes e incapacidad. Farmacia solo ve la fórmula.

### Permisos médicos y cambios de cita
```
Médico solicita permiso (≥ 7 días antes; día completo o media jornada) ─▶ PENDIENTE ─▶ coordinación APRUEBA o RECHAZA
Aprobado ─▶ el médico sale de la agenda en ese horario ─▶ sus citas pasan a «Por reprogramar»
Recepción propone nuevo día/hora (el turno queda apartado) ─▶ el paciente ACEPTA (la cita se mueve) o RECHAZA
```
- Las incapacidades y calamidades las registra la coordinación sin preaviso y quedan aprobadas.
- El paciente también puede elegir por su cuenta otro turno libre (con 24 horas de anticipación, o en cualquier momento si su cita quedó por reprogramar).

### Auditoría
- La historia es única por paciente; un médico solo la abre si tiene o tuvo una cita asignada con él. Los intentos sin relación de atención se bloquean y quedan marcados en la auditoría.
- Se registran ingresos, intentos fallidos, cierres de sesión, registros, verificación de identidad, cambios en usuarios, cada movimiento de una cita, el triaje, cada consulta y cada apertura de una historia.
- Quien tiene citas o historia no se puede eliminar (la historia se conserva); se desactiva.
- Los paneles cierran la sesión tras 30 minutos sin actividad (`app.minutos_inactividad`).

### Reglas de la agenda
- Turnos de 30 minutos. Lunes a viernes 7:00 a.m. – 6:00 p.m., sábados 8:00 a.m. – 1:00 p.m., domingos sin consulta.
- La agenda es por médico: en cada turno una especialidad recibe tantos pacientes como médicos suyos estén trabajando (sin permiso aprobado). Cada médico tiene un cupo diario (16 citas en especialidades básicas, 8 en las demás).
- Solo se agendan especialidades con al menos un médico activo; al confirmar o mover una cita se valida que el médico esté libre.
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

## Recordatorios automáticos

El script `tareas/recordatorios.php` avisa por el portal y por correo a los pacientes con cita confirmada para el día siguiente (una sola vez por cita). Prográmelo a diario en el **Programador de tareas de Windows** (Crear tarea básica → Diaria → Iniciar un programa):

```
Programa:   C:\xampp\php\php.exe
Argumentos: C:\xampp\htdocs\proyecto_productivo\tareas\recordatorios.php
```

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
- Recuperación de contraseña por correo con enlace de un solo uso que vence en 60 minutos (solo se guarda el hash del token).
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
