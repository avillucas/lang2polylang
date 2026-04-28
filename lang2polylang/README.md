# Lang2Polylang Linker

**Lang2Polylang** es un plugin de utilidad para WordPress diseñado específicamente para sitios que han migrado de una estructura de URLs manual (usando sufijos como `-es`) a un sistema gestionado por **Polylang**.

## Descripción

El plugin automatiza la vinculación de traducciones. Busca páginas cuyo slug termine en `-es` (ej. `vinedo-adrianna-es`), identifica su contraparte en inglés (`vinedo-adrianna`), les asigna el idioma correspondiente en Polylang y crea la relación de traducción entre ambas.

## Requisitos

- WordPress 5.0 o superior.
- Plugin **Polylang** (Gratuito o Pro) instalado y activo.
- Idiomas "English" (en) y "Español" (es) configurados en Polylang.

## Instalación

1. Descarga o copia el archivo `lang2polylang.php` en tu carpeta `/wp-content/plugins/lang2polylang/`.
2. Activa el plugin desde el menú de **Plugins** de WordPress.
3. Asegúrate de que Polylang esté configurado antes de ejecutar el proceso.

## Modo de Uso

1. Dirígete a **Herramientas > Lang2Polylang** en tu escritorio de WordPress.
2. Verás un resumen de lo que hará el plugin.
3. Haz clic en **"Iniciar Vinculación Ahora"**.
4. El sistema listará cada página procesada y confirmará si la vinculación fue exitosa.

## Advertencias

- **Backup:** Realiza una copia de seguridad de tu base de datos antes de usar este plugin, ya que modifica relaciones de taxonomías de forma masiva.
- **Slugs:** El plugin asume que el slug en español es exactamente igual al de inglés seguido de `-es`. Si hay variaciones (ej. `/contact/` vs `/contacto-es/`), la vinculación manual seguirá siendo necesaria.
