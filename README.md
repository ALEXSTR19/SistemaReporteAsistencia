# Sistema PHP para registros de asistencia SmartPSS Lite

Este proyecto lee la tabla `attendancerecordinfo` que llena SmartPSS Lite en MySQL y permite:

- Consultar registros.
- Editar registros mediante correcciones seguras.
- Marcar registros como eliminados sin borrar la tabla original.
- Generar reporte HTML y PDF con logos y encabezado oficial.
- Mantener auditoría de ediciones, eliminaciones y restauraciones.

## Importante

El sistema NO modifica la tabla original `attendancerecordinfo`. Las ediciones y eliminaciones se guardan en tablas separadas:

- `app_attendance_overrides`
- `app_audit_log`
- `app_users`

Esto protege los registros que genera SmartPSS Lite.

## Instalación rápida en XAMPP/WAMP/Laragon

1. Copia esta carpeta a tu servidor web, por ejemplo:
   - `C:\xampp\htdocs\smartpss_php_sistema`

2. Edita `config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'asistencias_db');
define('DB_USER', 'root');
define('DB_PASS', 'TU_PASSWORD');
define('TABLE_RAW', 'attendancerecordinfo');
```

3. En MySQL Workbench ejecuta:

```sql
SOURCE C:/xampp/htdocs/smartpss_php_sistema/sql/install.sql;
```

O copia y pega el contenido de `sql/install.sql`.

4. Verifica que FPDF esté incluida de forma local para reportes PDF:

```text
lib/fpdf/fpdf.php
```

El sistema carga FPDF directamente desde `lib/fpdf/`; no es necesario ejecutar `composer install` para esta librería. Si reemplazas la librería, conserva el archivo principal en `lib/fpdf/fpdf.php`.

5. Abre en el navegador:

```text
http://localhost/smartpss_php_sistema/login.php
```

Usuario inicial:

```text
admin
```

Contraseña inicial:

```text
admin123
```

Cambia esa contraseña después de instalar.

## Logos

Reemplaza estos archivos con tus logos reales:

- `assets/logo_left.png`
- `assets/logo_right.png`

## Nota sobre fechas

SmartPSS Lite suele guardar `AttendanceDateTime` como timestamp en milisegundos. El sistema lo convierte a formato normal para mostrarlo en pantalla y PDF.
