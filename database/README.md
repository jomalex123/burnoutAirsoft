# Base de datos

## Entornos

La aplicacion lee el entorno desde la variable `BURNOUT_ENV`. Valores esperados:

- `local`
- `pre`
- `pro`

En PRE y produccion, guarda las credenciales fuera del directorio publico:

```text
PRE:        Directorio principal/private/env.php
Produccion: Directorio principal/private/burnoutairsoft/env.php
```

La aplicacion busca el fichero en este orden:

1. Ruta definida por la variable `BURNOUT_ENV_FILE`, si existe.
2. Si corre desde `httpdocs`, usa `../private/env.php`.
3. Si corre desde `burnoutairsoft.com`, usa `../private/burnoutairsoft/env.php`.
4. `config/env.php`, solo como fallback local o transitorio.
5. `config/env.example.php`, solo como plantilla sin credenciales reales.

Si mantienes temporalmente `config/env.php` en PRE o produccion, no sera visible por navegador porque `.htaccess` bloquea tanto `env.php` como el directorio `config/`. Aun asi, la opcion recomendada es mover los ficheros reales a las rutas privadas anteriores.

Para desarrollo local en Docker, el contenedor web usa `docker/env.php` mediante `BURNOUT_ENV_FILE`. Los datos deben coincidir con la conexion local de `config/.env`, salvo el host: dentro de Docker debe ser `db`; desde la maquina anfitriona debe ser `127.0.0.1`.

La base local se levanta con MySQL 8.4:

```bash
docker compose up -d db
```

El puerto local publicado es `3306`.

## Crear tablas

Ejecuta `database/schema.sql` en cada base de datos: Local, PRE y PRO.

En local con Docker, `database/schema.sql` se importa automaticamente la primera vez que MySQL inicializa el volumen porque esta montado en `/docker-entrypoint-initdb.d/01-schema.sql`.

Si necesitas cargarlo manualmente sobre una base ya creada:

```bash
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < database/schema.sql
```

Si escribes el `USE` manualmente, el nombre de la base debe ir entre backticks:

```sql
USE `11364681_burnoutairsoft`;
```

## Migraciones

Las migraciones de `database/migrations/` son para bases ya existentes que parten de un esquema anterior. Antes de aplicarlas, comprueba si el cambio ya esta incluido en `database/schema.sql`.

Ejemplo:

```bash
docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026_05_07_add_event_capacity.sql
```

## Crear usuario admin

Con el entorno apuntando a la base de datos correcta:

```bash
php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```

Si no hay PHP local, usa el PHP del contenedor web:

```bash
docker compose run --rm web php scripts/create_admin_user.php admin "password-seguro" "Burnout Admin"
```
