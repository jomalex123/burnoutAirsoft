# Base de datos

## Entornos

La aplicacion lee el entorno desde la variable `BURNOUT_ENV`. Valores esperados:

- `local`
- `pre`
- `pro`

Copia `config/env.example.php` a `config/env.php` y rellena las contrasenas reales. `config/env.php` esta ignorado por Git.

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
