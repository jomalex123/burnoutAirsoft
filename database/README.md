# Base de datos

## Entornos

La aplicacion lee el entorno desde la variable `BURNOUT_ENV`. Valores esperados:

- `local`
- `pre`
- `pro`

En PRE y produccion, guarda las credenciales fuera del directorio publico:

```text
Directorio principal/private/burnoutairsoft/env.php
```

La aplicacion busca el fichero en este orden:

1. Ruta definida por la variable `BURNOUT_ENV_FILE`, si existe.
2. `../private/burnoutairsoft/env.php`, subiendo desde el directorio publico del sitio.
3. `../private/env.php`.
4. `config/env.php`, solo como fallback local o transitorio.
5. `config/env.example.php`, solo como plantilla sin credenciales reales.

Si mantienes temporalmente `config/env.php` en PRE o produccion, no sera visible por navegador porque `.htaccess` bloquea tanto `env.php` como el directorio `config/`. Aun asi, la opcion recomendada es mover el fichero real a `private/burnoutairsoft/env.php`.

Para desarrollo local puedes copiar `config/env.example.php` a `config/env.php` y rellenar tus credenciales locales. `config/env.php` esta ignorado por Git.

## Crear tablas

Ejecuta `database/schema.sql` en cada base de datos: Local, PRE y PRO.

Para local, si lo lanzas desde consola, selecciona antes la base de datos o anade el `USE` manualmente:

```bash
D:\xampp\mysql\bin\mysql.exe -u root 11364681_burnoutairsoft < database\schema.sql
```

El nombre `11364681_burnoutairsoft` debe ir entre backticks si escribes el `USE` manualmente:

```sql
USE `11364681_burnoutairsoft`;
```

## Crear usuario admin

Con el entorno apuntando a la base de datos correcta:

```bash
php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```

En XAMPP para Windows, si `php` no esta en el PATH, usa la ruta completa:

```bash
C:\xampp\php\php.exe scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```
