# WordPress con Docker Compose

Este proyecto configura un entorno de WordPress y MariaDB utilizando Docker Compose.

## Requisitos

- Docker instalado.
- Docker Compose v2 (se usa el comando `docker compose`).

## Instalación

1. Asegúrate de que el archivo `.env` tenga las credenciales deseadas.
2. Inicia los contenedores:

   ```bash
   docker compose up -d
   ```

3. Accede a WordPress en: `http://localhost:8080`

## Estructura

- `db`: Contenedor con MariaDB 10.6.
- `wordpress`: Contenedor con WordPress (última versión).
- Volumen `db_data`: Persistencia de la base de datos.
- Volumen `wp_data`: Persistencia de los archivos de WordPress.
