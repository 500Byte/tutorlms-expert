# Rendimiento y SEO de Tutor LMS en producción

> Tutor no es "un plugin lento". Es un plugin que **carga assets en todas las páginas**, guarda su configuración en una sola opción autoload, y calcula progreso con varias consultas por curso. En un catálogo de 5 cursos eso no se nota; en un dashboard con 40 matrículas o en un listado con 12 tarjetas, sí. Esta referencia separa lo que se puede medir de lo que se supone, y da el fix por capa.

**Estado de verificación (2026-11-13).** Lo marcado como *verificado* se comprobó en este turno contra el código de Tutor LMS 4.2.1 descargado de wordpress.org, o contra documentación primaria (WordPress, web.dev, Google Search Central, Yoast, WooCommerce, IONOS). Lo marcado como *sin comprobar* no se afirma: se deja como hipótesis a validar en el sitio concreto.

---

## 0. Antes de tocar nada: línea base

Sin línea base no hay mejora, solo cambios. Captura esto en el mismo entorno (staging con datos parecidos a producción, no un WordPress vacío):

| Métrica | Dónde | Objetivo |
|---|---|---|
| TTFB (p75, móvil) | DevTools / WebPageTest / RUM | ≤ 0,8 s (guía web.dev) |
| LCP (p75, móvil) | CrUX, PSI, RUM | ≤ 2,5 s |
| INP (p75) | CrUX / RUM (Lighthouse no lo mide) | ≤ 200 ms |
| CLS (p75) | CrUX / PSI / RUM | ≤ 0,1 |
| Consultas y tiempo SQL | Query Monitor | comparar antes/después |
| Peso de autoload | `wp option list` + SQL | ver §1.4 |
| Peso de CSS/JS de Tutor | DevTools Network | ver §1.1 |

Plantilla mínima para pegar en el reporte:

```
PLANTILLA / FECHA:
  TTFB ___ ms | LCP ___ s | INP ___ ms | CLS ___
  Queries ___ | Query time ___ ms | Autoload ___ KB
  tutor-front.min.css cargado en home: sí/no
  Object cache: ___ (backend / hit rate)
```

Regla: mide **plantilla por plantilla**. La home, el archivo de cursos, una ficha de curso, una lección y el dashboard tienen costes distintos; promediarlos esconde el problema.

---

## 1. Rendimiento

### 1.1 Dónde se va el tiempo en Tutor (modelo de coste)

**a) Assets globales.** En Tutor 4.2.1, `Assets::frontend_scripts()` está enganchado a `wp_enqueue_scripts` y, salvo en el learning area, el dashboard y las páginas de registro, enquelea el path legacy sin mirar qué página es:

- `assets/css/tutor-front.min.css` — **141 KB** — sin condición
- `assets/js/tutor-front.js` — dependencias `jquery`, `wp-i18n`, `wp-date` — sin condición
- `assets/css/tutor-icon.min.css` — **16 KB** — vía `common_scripts`, también en front
- `assets/lib/plyr/plyr.css` — **32 KB** — este sí, solo en `is_single_course()`

Es decir: en la home de un sitio con Tutor instalado se descarga CSS/JS de curso que esa página no usa. *(Verificado en `classes/Assets.php` de 4.2.1.)*

Tutor 4 añadió un sistema nuevo (`tutor-core.min.css` + `tutor-core.js`) con CSS condicional por dashboard/learning area, pero el path legacy sigue activo por defecto fuera de esas pantallas.

**Fix:** no dequeue a ciegas. Si la página no renderiza nada de Tutor, corta el path legacy con su propio filtro (existe y se aplica en el core):

```php
// mu-plugin: solo cargar assets legacy de Tutor donde hacen falta.
// Añade las plantillas reales del sitio que no renderizan nada de Tutor.
add_filter( 'tutor_should_load_legacy_scripts', function ( $load ) {
    if ( is_front_page() || is_home() ) {
        return false;
    }
    return $load;
} );
```

Verifica después: la home no debe pedir `tutor-front.min.css`, y una ficha de curso, una lección y el dashboard deben seguir funcionando (botones, acordeón, progreso).

**b) Google Fonts externo.** El sistema nuevo enqueuea `https://fonts.googleapis.com/css2?family=Inter...&display=swap` (`tutor-google-fonts`). Es una conexión a un tercero en el critical path. *(Verificado en `classes/Assets.php`.)*

**Fix:** si la tipografía ya está local en el tema, dequea `tutor-google-fonts` y declara `Inter` con `@font-face` local + `font-display: swap` + `preload` del weight que use el H1. No cambies la familia sin decisión de marca.

**c) Progreso por curso = N+1.** `tutor_utils()->get_course_completed_percent()` consulta lecciones completadas, contenidos del curso, intentos de quiz y assignments. Se llama en plantillas de loop:

- `templates/loop/course-continue.php`
- `templates/loop/enrolled-course-progress.php`
- `templates/dashboard/courses/course-card.php`
- `templates/single/common/header.php`

*(Verificado por grep en 4.2.1.)* En un listado de 12 cursos son 12 cálculos. En el dashboard de un alumno con 30 cursos, 30.

**Fix:** primero mide con Query Monitor en `/dashboard/` y en el listado. Si domina el tiempo, cachea el porcentaje por `user_id + course_id` en un transient con invalidación al completar lección o curso, no "para siempre". Plantilla:

```php
function ipt_course_percent_cached( int $course_id, int $user_id ): int {
    $key = "ipt_pct_{$user_id}_{$course_id}";
    $val = get_transient( $key );
    if ( false === $val ) {
        $val = (int) tutor_utils()->get_course_completed_percent( $course_id, $user_id );
        set_transient( $key, $val, HOUR_IN_SECONDS );
    }
    return $val;
}
// Invalidar al completar lección/curso (hooks de Tutor) y al cambiar de curso.
```

Sin object cache persistente, ese transient vive en `wp_options`: mídelo antes de celebrar (§1.2).

**d) `TutorCache` no cruza requests.** `cache/TutorCache.php` es un array estático por request ("run-time cache"). Acelera llamadas repetidas dentro de la misma página, pero **no sustituye** un object cache persistente. Los caches que sí persisten usan `set_transient` (`cache/AbstractCache.php`). *(Verificado en el código.)*

Consecuencia práctica: si el hosting no tiene Redis/Memcached, "Tutor ya cachea" es falso entre páginas.

**e) Reportes y analíticas.** El addon Reports es de **Tutor Pro** (el readme lo lista como addon premium); el core trae `InstructorMetricsAdapter` y las tablas `tutor_orders` / `tutor_order_items`. Los reportes con joins sobre pedidos, intentos y usuarios son la consulta más cara del admin. No los optimices a ciegas: `EXPLAIN` + slow log. Y no los abras en producción mientras corre una campaña.

**f) Elementor y su CSS.** Elementor compila CSS por página a `/wp-content/uploads/elementor/css/post-{id}.css` cuando el método es **External File**; con **Internal Embedding** lo inyecta inline en cada página. La propia documentación de Elementor describe ambos y recomienda el stylesheet independiente. *(Verificado en la página de settings de Elementor.)*

Implicación para fichas de curso: cada curso tiene su `post-{id}.css`; son muchos archivos pequeños. Eso está bien con HTTP/2 y caché de navegador, y es peor si cambias a inline para "ahorrar una request". No cambies el método para perseguir un número de Lighthouse sin medir el HTML.

### 1.2 Object cache (Redis / Memcached)

WordPress, por defecto, cachea **solo durante el request**; `WP_CACHE` en `wp-config.php` no lo cambia desde 2.5. Para persistencia hace falta un drop-in (`object-cache.php`) con backend real (Redis, Memcached, SQLite Object Cache). *(Verificado en developer.wordpress.org.)*

Qué gana Tutor con esto:
- `get_option('tutor_option')` deja de pegar a la tabla en cada request (aunque sigue cargándose por autoload, §1.4).
- `WP_Query` repetidas del currículo se sirven de RAM.
- Los transients de §1.1c pasan a RAM en vez de `wp_options`.

Cómo verificar que **está** funcionando, no solo instalado. El test limpio es escribir en un proceso y leer en otro (dos invocaciones de WP-CLI = dos requests):

```bash
wp cache set ipt_probe 1 && wp cache get ipt_probe   # debe devolver 1
```

Si la segunda invocación devuelve `false`, el backend no persiste entre procesos. Si instalas el plugin y el drop-in no está en `wp-content/object-cache.php`, no hay nada. En Query Monitor, la pestaña de cache debe mostrar hits/misses con backend persistente.

### 1.3 Page cache y exclusiones (lo que rompe un LMS)

La page cache sirve HTML anónimo. En Tutor eso cubre: home, archivo de cursos, ficha de curso pública, páginas de marketing. **No** cubre nada que dependa de sesión.

Excluye explícitamente:

```
/dashboard/            (o el slug real de tutor_dashboard_page_id)
/escritorio/           (si el cliente usa slug traducido)
/cursos/*/lessons/*    (learning area: progreso, notas, next/prev)
/cursos/*/quizzes/*    (intento en curso, timer)
/carrito/ /checkout/ /mi-cuenta/
/wp-admin/ (nunca)
cualquier URL con ?tutor_dashboard_page=
```

Verifica en el **edge**, no en el panel del plugin: pide una lección con `curl` sin cookie y con cookie de alumno; el HTML no puede ser idéntico. Un listado de cursos cacheado con "Continuar" de otro usuario es una fuga, no una optimización.

Regla: si el HTML cambia según `is_user_logged_in()` o según matrícula, no se cachea públicamente.

### 1.4 Autoload: la opción que se paga en cada request

Tutor crea `tutor_option` en la instalación con `update_option()` sin `$autoload` explícito (`classes/Tutor.php:712`). En WordPress moderno eso se guarda como `auto`, y `auto` **sí** entra en `wp_autoload_values_to_autoload()` → se carga en cada request. *(Verificado en el código de Tutor y en la referencia de `add_option`/`wp_autoload_values_to_autoload`.)*

El array contiene decenas de claves (settings de cursos, emails, addons, permisos). No es un bug de Tutor por sí solo, pero crece con cada addon y cada opción que se guarde ahí.

Medir:

```bash
wp option get tutor_option --format=json | wc -c
wp option list --autoload=on --format=count
wp option list --autoload=on --format=total_bytes
```

Y para ver el top de culpables:

```sql
SELECT option_name, LENGTH(option_value) AS bytes
FROM wp_options
WHERE autoload IN ('yes','on','auto','auto-on')
ORDER BY bytes DESC
LIMIT 20;
```

Si el total pasa de ~1 MB, el problema no es solo Tutor: es el conjunto. El changelog incluido en el plugin registra un fix de este tipo ("Removed the autoload option from `email_template_data` to improve site load performance. (Pro)"), señal de que el patrón aparece. *(Verificado en `readme.txt`.)*

**Fix:** no pongas `tutor_option` a `off` sin medir: se lee en frontend y cada lectura pasaría a ser una query. Lo correcto es (a) quitar de autoload lo que no se lee en frontend, (b) auditar plugins que meten payloads grandes, (c) no guardar logs/transients como opciones.

### 1.5 Imágenes

- Tamaño correcto: usa `srcset`/`sizes`; una ficha de curso no necesita el original de 2400 px.
- Formato moderno con fallback (WebP/AVIF). Elementor y los optimizadores generan derivados; verifica que la imagen del hero y las miniaturas de curso se sirvan en WebP, no que existan en disco.
- Dimensiones explícitas (`width`/`height` o `aspect-ratio`) para no mover el layout: es la causa nº1 de CLS en fichas con imagen + precio + CTA.
- La imagen del **LCP** (normalmente el hero de la ficha o del home) va `fetchpriority="high"` y **nunca** `loading="lazy"`. Elementor expone "Optimized Image Loading" con exactamente ese comportamiento (`fetchpriority="high"` en LCP y `lazy` bajo el fold). *(Verificado en la doc de Elementor.)*

### 1.6 Vídeo: YouTube, Vimeo, HTML5 y Bunny

Tutor guarda el vídeo de lección en `_video` con `source` y, según el caso, `source_youtube`, `source_vimeo`, `source_html5`, `source_external_url`, `poster`. En 4.2.1 existe además `source_bunnynet` (Bunny Stream). *(Verificado en `classes/Utils.php`.)*

Reglas:
- Auto-hospedar MP4 grandes en shared hosting satura I/O y ancho de banda. Usa YouTube/Vimeo no listados o un host de streaming, y deja el MP4 local solo para clips cortos.
- Un `<iframe>` de YouTube descarga player y cookies de terceros en la carga inicial: si el vídeo no es el LCP, cárgalo con click-to-load (poster + botón) y sustituye el iframe al clic. No lo hagas en la lección donde el vídeo *es* el contenido principal sin comprobar que el alumno sigue pudiendo completar (`control_video_lesson_completion`).
- Bunny: si el cliente ya paga Bunny Stream, `source_bunnynet` evita el iframe de YouTube. Confirma en la lección real que el player carga y que el progreso se registra.
- `poster` siempre: evita el hueco blanco y el CLS al montar el player.

### 1.7 Límites de PHP en IONOS

IONOS documenta `memory_limit` (256 MB en el ejemplo de su guía), `max_execution_time` (60 s) y `NPROC` por plan; además, **el límite del paquete manda sobre lo que pongas en `php.ini`**. Los planes WordPress usan "Performance Levels" fijados al plan. Desde contratos del 21/07/2026 el `php.ini` va en `/home/www/`; en contratos anteriores aplica por directorio. *(Verificado en el help center de IONOS.)*

Consecuencias para Tutor:
- Importar currículos grandes, exportar reportes o regenerar CSS de Elementor puede tocar `max_execution_time`. Hazlo por WP-CLI (sin límite web) o por lotes.
- Subir vídeos o ZIP de attachments grandes depende de `upload_max_filesize`/`post_max_size`; si el curso promete un ZIP de 200 MB, no va a entrar por el editor.
- Si el sitio vive en un plan pequeño, el techo real es NPROC + memoria, no la versión de PHP. Mide antes de pedir "más RAM".

### 1.8 Cron

Tutor programa `tutor_once_in_day_run_schedule` en `twicedaily` al instalarse (`classes/Tutor.php:723`). WP-Cron no corre solo: se dispara **en cada page load**, y con page cache el tráfico anónimo puede no dispararlo. *(Verificado en el código y en la doc de WP-Cron.)*

Síntomas típicos: emails de Tutor que salen tarde, tareas que "no se ejecutan" en un sitio con cache.

Fix estándar, en este orden:

```php
// wp-config.php — desactiva el cron por visita
define( 'DISABLE_WP_CRON', true );
```

```bash
# crontab del servidor (cada 5 min)
*/5 * * * * cd /ruta/wp && wp cron event run --due-now --quiet
```

Comprueba la cola:

```bash
wp cron event list --fields=hook,next_run_relative
```

No desactives `DISABLE_WP_CRON` sin haber creado el cron del sistema: pasarías de "tarde" a "nunca".

### 1.9 Lo que NO se hace

- **No cachear** dashboard, learning area, carrito, checkout, cuenta ni intento de quiz.
- **No dequeues jQuery global** para "mejorar INP": Tutor y WooCommerce lo declaran como dependencia; romperás quiz, acordeón y add-to-cart.
- **No minifiques/combines a ciegas** el JS de Tutor: `tutor-core.js` y `tutor-front.js` localizan datos (`_tutorobject`); un combine mal hecho rompe nonces y rutas AJAX.
- **No pongas `posts_per_page => -1`** en una plantilla pública tuya para "mostrar todos los cursos". Tutor ya lo hace en sitios concretos del core; no añadas otro.
- **No confundas** "Lighthouse 95 en desktop" con el objetivo. El veredicto es p75 móvil.

---

## 2. Cómo medir

### 2.1 Core Web Vitals: umbrales y cómo se leen

Umbrales oficiales (p75, móvil y desktop por separado): **LCP ≤ 2,5 s**, **INP ≤ 200 ms**, **CLS ≤ 0,1**. *(Verificado en web.dev/articles/vitals.)*

Dos trampas:
- **Lighthouse no mide INP** (no hay interacción real); usa **TBT** como proxy de laboratorio. El INP real se lee en campo (CrUX, RUM, Search Console).
- **TTFB no es Core Web Vital**, pero condiciona LCP. Guía: ≤ 0,8 s bueno, > 1,8 s pobre. *(Verificado en web.dev/articles/ttfb.)*

En un LMS, el INP suele venir de JS de terceros (chat, pixel, player) y de handlers pesados en el dashboard, no del HTML.

### 2.2 Query Monitor

Úsalo en staging con un usuario real, no como admin vacío. Mira:

1. **Queries por plugin/tema** en una ficha de curso, una lección y el dashboard.
2. **Slow queries** (las que Query Monitor marca como lentas) y **duplicadas** (mismo SQL ejecutado N veces).
3. **Hooks** caros y `wp_remote_get` en el request.
4. **Cache** (hits/misses) y **autoload**.

Nota: activar `SAVEQUERIES` tiene coste. No lo dejes encendido en producción.

### 2.3 Slow query log

En MySQL/MariaDB, activa el log y ordena por tiempo. Busca joins sobre `tutor_quiz_attempts`, `tutor_orders`, `tutor_order_items`, `postmeta` con `meta_key` no indexado, y `ORDER BY` sobre `menu_order` sin índice útil.

```sql
-- ejemplo de inspección (ajusta prefijo)
EXPLAIN SELECT ID FROM wp_posts
WHERE post_type = 'lesson' AND post_parent = 123
ORDER BY menu_order;
```

Si `EXPLAIN` dice `Using filesort` o `type: ALL` sobre una tabla que crece (intentos, pedidos), ahí está el problema. Índice compuesto sobre las columnas del `WHERE` + `ORDER BY` antes de tocar PHP.

### 2.4 WP-CLI: chequeos rápidos y reproducibles

```bash
# autoload
wp option list --autoload=on --format=total_bytes
wp option list --autoload=on --format=count

# transients acumulados
wp transient list --format=count

# cron
wp cron event list --fields=hook,next_run_relative

# ¿el object cache persiste entre requests?
wp cache set ipt_probe 1 && wp cache get ipt_probe

# estructura del curso (del propio repo)
wp eval-file scripts/check-course.php 1499
```

`wp option list --autoload=on` acepta `on|off` y `--format=total_bytes` (verificado con WP-CLI 2.12). `wp cron event run --due-now` también.

### 2.5 `analyze-performance`

**No está disponible en este repo ni en esta sesión.** No existe script con ese nombre en `scripts/` ni binario en el entorno. Si un harness futuro la expone, úsala como capa extra; mientras tanto, el equivalente es:

- Query Monitor para SQL y hooks.
- `wp option list`/`wp transient list` para autoload y basura.
- PSI/CrUX para campo y Lighthouse para laboratorio.
- `EXPLAIN` + slow log para las consultas que dominan.

No inventes una salida de `analyze-performance` ni la cites como fuente.

---

## 3. SEO de cursos

### 3.1 Slugs y permalinks: lo que Tutor decide por ti

Valores por defecto y comportamiento real en 4.2.1:

| Elemento | Valor | Nota |
|---|---|---|
| Base de curso | `courses` (`course_permalink_base`) | configurable en ajustes |
| Base de lección | `lessons` (`lesson_permalink_base`) | la lección cuelga del curso |
| Taxonomía de categoría | `course-category` | slug de rewrite `course-category` |
| Taxonomía de tag | `course-tag` | slug `course-tag` |
| URL de lección | `/{base-curso}/{slug-curso}/lessons/{slug-leccion}/` | regla de rewrite propia |

*(Verificado en `classes/Post_types.php`, `classes/Rewrite_Rules.php`, `classes/Options_V2.php`.)*

**Trampa de SEO:** al cambiar la base de permalink, Tutor marca un transient y en el siguiente `init` ejecuta `update_option( 'permalink_structure', '/%postname%/' )` + `flush_rewrite_rules()`. Es decir, **puede sobrescribir la estructura de permalinks del sitio** (por ejemplo, si el blog usaba `/blog/%postname%/`). *(Verificado en `classes/Permalink.php`.)*

Antes de tocar la base: anota `permalink_structure`, haz backup, y reverifica después. Si el sitio tiene blog, no permitas que Tutor lo pise.

### 3.2 Lecciones, quizzes y assignments no se listan públicamente

Desde 4.2.1, `block_public_listing_of_protected_types()` intercepta la query principal: si pide `lesson`, `tutor_quiz` o `tutor_assignments` fuera de una página singular, fuerza 404 (`status_header(404)`, `nocache_headers()`). *(Verificado en `classes/Post_types.php`.)*

Consecuencias:
- La página singular de lección **sí** existe y se renderiza; lo que no existe es un archivo/listado de lecciones.
- No esperes que un sitemap incluya "archivo de lecciones": no hay.
- No construyas enlaces internos a listados de `lesson`; enlaza a la lección concreta o al curso.

### 3.3 Schema de Course: la verdad incómoda

Dos hechos que cambian la estrategia:

1. **Google retiró el rich result de "course info".** La documentación se eliminó el 2025-09-09 y el tipo dejó de mostrarse en resultados. *(Verificado en el changelog de Google Search Central.)* Añadir `Course` JSON-LD **no** te da una tarjeta en Google.
2. **Tutor LMS 4.2.1 no emite JSON-LD.** No hay `application/ld+json` ni `@context` de schema.org en el plugin gratuito. *(Verificado por grep en el código.)* Y **Yoast SEO no tiene pieza de `Course`**: su lista oficial de piezas no lo incluye (y un hilo de soporte lo confirma). *(Verificado en developer.yoast.com y en el foro de soporte de Yoast.)*

Entonces, ¿para qué el schema? Para claridad semántica y para que sistemas que sí lo consumen (búsqueda con IA, agregadores, herramientas internas) entiendan el curso. Es válido, pero no es una palanca de rich results.

Si se implementa, que sea **custom y fiel al contenido visible**:

```php
add_action( 'wp_head', function () {
    if ( ! is_singular( 'courses' ) ) {
        return;
    }
    $id   = get_queried_object_id();
    $raw  = tutor_utils()->get_raw_course_price( $id ); // resuelve WC / EDD / Tutor
    $cost = (float) $raw->sale_price > 0 ? (float) $raw->sale_price : (float) $raw->regular_price;

    $data = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => get_the_title( $id ),
        'description' => wp_strip_all_tags( get_the_excerpt( $id ) ),
        'provider'    => array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        ),
        'url' => get_permalink( $id ),
    );

    $currency = get_option( 'woocommerce_currency' ); // no inventar moneda
    if ( $cost > 0 && $currency ) {
        $data['offers'] = array(
            '@type'         => 'Offer',
            'price'         => $cost,
            'priceCurrency' => $currency,
            'availability'  => 'https://schema.org/InStock',
            'url'           => get_permalink( $id ),
        );
    }

    echo '<script type="application/ld+json">'
        . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>';
}, 20 );
```

> Nota de modelo verificada en 4.2.1: los metas de precio son `tutor_course_price` y `tutor_course_sale_price` (**sin** guion bajo inicial), y el producto Woo se guarda en `_tutor_course_product_id`. Lee el precio con `get_raw_course_price()`/`get_course_price()`, no con un meta hardcodeado.

Reglas: un solo `Course` por URL, `price` solo si el curso es de pago, `provider` real (no inventar NIU ni acreditaciones), y nada de `aggregateRating` sin reseñas verificables. Un schema que contradice la página es peor que no tener schema.

### 3.4 Títulos, meta y contenido duplicado

- **Título**: `Nombre del curso | Marca`. Sin precio ni "oferta" en el `<title>`; envejece y ensucia la SERP.
- **Meta description**: qué se aprende, para quién, y qué se obtiene (certificado). No repitas el H1 palabra por palabra.
- **Duplicado real en un LMS**: la descripción del curso copiada en cada lección, o la misma descripción en curso y bundle. Una lección con el mismo texto que la ficha compite contra la ficha. Escribe la lección para la lección; deja el resumen comercial en el curso.
- **Preview de lección**: si la lección es gratis como muestra, es una página indexable legítima; si es un duplicado del curso, `noindex` o canonical al curso.
- **Canonical**: una URL por curso. Si hay variantes (`?tutor_course_filter`, paginación del archivo), canonical a la limpia y evita indexar filtros.

### 3.5 Sitemap e indexación

- Yoast genera sitemaps de los post types públicos; los que estén en `noindex` **no** aparecen. Los sitemaps llevan `X-Robots-Tag: noindex, follow`. *(Verificado en la doc de Yoast.)*
- Con `courses` público y `has_archive => true`, el archivo de cursos y las fichas deben salir en el sitemap. Verifícalo abriendo `/sitemap_index.xml` y buscando `courses-sitemap.xml`.
- **No indexables**: dashboard, learning area, carrito, checkout, cuenta, páginas de registro, resultados de quiz. Comprueba el `noindex` real en el HTML, no solo el ajuste del plugin.
- **Enlaces internos**: cada curso debe enlazar a su categoría, a 2-3 cursos relacionados y desde el archivo. Un curso huérfano (solo accesible por el sitemap) tarda en posicionarse.
- Si usas slugs traducidos (`/cursos/`), decide una vez y no cambies: cada cambio es un `flush_rewrite_rules` y 301s pendientes.

---

## 4. Analytics

### 4.1 GA4: eventos que importan

Para un curso vendido por WooCommerce, el embudo mínimo:

| Acción | Evento GA4 | Parámetros clave |
|---|---|---|
| Ver ficha de curso | `view_item` | `currency`, `value`, `items[]` |
| Añadir al carrito | `add_to_cart` | `currency`, `value`, `items[]` |
| Iniciar checkout | `begin_checkout` | `currency`, `value`, `items[]` |
| Compra | `purchase` | `transaction_id`, `value`, `currency`, `items[]` |
| Reembolso | `refund` | `transaction_id`, `value`, `items[]` |

`purchase` **exige** `transaction_id`, `value` (suma de precio × cantidad), `currency` y `items`; `items[]` requiere `item_id` o `item_name`. *(Verificado en la doc de ecommerce de GA4.)*

Buenas prácticas que evitan el clásico "el doble de compras":
- Dispara `purchase` **una vez por pedido**, no en cada recarga de la thank-you page. Patrón idempotente en servidor:

```php
add_action( 'woocommerce_order_status_processing', 'ipt_track_purchase_once', 10, 1 );
add_action( 'woocommerce_order_status_completed', 'ipt_track_purchase_once', 10, 1 );

function ipt_track_purchase_once( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_ipt_ga4_purchase' ) ) {
        return; // ya enviado: reintentos y estados repetidos no duplican
    }
    // Enviar por Measurement Protocol o guardar para el dataLayer.
    $order->update_meta_data( '_ipt_ga4_purchase', current_time( 'c' ) );
    $order->save();
}
```

  Si además hay un tag de GA4 en el navegador para `purchase`, tienes dos fuentes. Elige una y documenta cuál.
- `value` debe ser el total real del pedido (con impuestos/descuentos según tu política), no el precio de lista.
- Para cursos gratuitos, `purchase` no aplica: usa `sign_up` o un evento custom de inscripción; no infles ingresos con 0.

### 4.2 Meta Pixel

No verifiqué la documentación de Meta en este turno, así que aquí solo va lo que no depende de detalles de su API:

- Un solo `Purchase` por transacción, con el mismo identificador de pedido que uses en GA4.
- El píxel no debe dispararse antes del consentimiento cuando la norma aplicable lo exige.
- Si usas píxel + Conversions API, la deduplicación se configura en el Events Manager; verifícala ahí, no la asumas.

Cualquier detalle de nombres de eventos o parámetros: consúltalo en la doc vigente de Meta antes de implementarlo.

### 4.3 Consentimiento (Consent Mode v2)

Google exige cuatro señales: `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, y el `default` debe ejecutarse **antes** de cualquier `config` o `event`. Para banners asíncronos, `wait_for_update` da margen a la CMP. *(Verificado en la doc de consent mode.)*

```js
gtag('consent', 'default', {
  ad_storage: 'denied',
  analytics_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied',
  wait_for_update: 500
});
```

Regla de oro: el consentimiento se actualiza en la página donde el usuario acepta, antes de navegar. Si se actualiza al descargar la página, se pierden eventos.

---

## 5. Checklist de lanzamiento

### Por curso (antes de publicar)

- [ ] Título y meta description escritos a mano (no autogenerados con el precio).
- [ ] Slug corto, en el idioma del sitio, sin cambios posteriores.
- [ ] Categoría (`course-category`) asignada; no queda en "sin categoría".
- [ ] Imagen destacada con dimensiones correctas y derivados generados; es el LCP de la ficha.
- [ ] Precio y `_tutor_course_product_id` coherentes con WooCommerce.
- [ ] Currículo con `menu_order` sin duplicados (afecta next/prev y progreso).
- [ ] Cada lección tiene `_tutor_course_id_for_lesson` (sin él no cuenta para progreso).
- [ ] Vídeo: `_video` con `source` correcto y `poster`; si es Bunny, `source_bunnynet`.
- [ ] Adjuntos (`_tutor_attachments`) existen y el ZIP no excede `upload_max_filesize`.
- [ ] Certificado asignado y `course_completion_process` decidido (`strict` vs `flexible`).
- [ ] La ficha responde 200 sin cookie y no muestra datos de otro usuario.
- [ ] Enlaces internos: categoría + 2 relacionados + CTA desde el archivo.
- [ ] `view_item` y `add_to_cart` se disparan una vez (verificado en DebugView).

### Por sitio (antes de campaña)

- [ ] TTFB p75 ≤ 0,8 s en la ficha de curso.
- [ ] `tutor-front.min.css` **no** se carga en home/landing que no usan Tutor (o está justificado).
- [ ] Object cache persistente activo y con hits medidos.
- [ ] Page cache con dashboard/learning area/checkout/cart/cuenta excluidos y verificado por `curl`.
- [ ] Autoload total medido y documentado; sin opciones gigantes nuevas.
- [ ] `tutor_option` no contiene logs ni payloads que crezcan.
- [ ] Cron del sistema ejecutando `wp cron event run --due-now` (o WP-Cron sano y medido).
- [ ] `DISABLE_WP_CRON` coherente con lo anterior: nunca activado sin cron del sistema.
- [ ] Sitemap incluye `courses-sitemap.xml`; dashboard y checkout con `noindex` real.
- [ ] Un solo `Course` JSON-LD por ficha (si se implementó) y sin contradicciones con el HTML.
- [ ] `purchase` dispara una vez por pedido; `transaction_id` = ID de pedido.
- [ ] Consent Mode por defecto en `denied` antes de los tags.
- [ ] Backup y `permalink_structure` anotados antes de tocar bases de permalink.

---

## 6. Síntoma → causa → fix

| Síntoma | Causa | Fix |
|---|---|---|
| Home lenta con Tutor instalado y sin cursos en esa página | `frontend_scripts()` enqueuea `tutor-front.min.css/js` en todo el front | `tutor_should_load_legacy_scripts` → false en páginas sin Tutor; verificar render |
| CSS/JS de curso duplicado o "no aplicado" tras cachear | Legacy (`tutor-front`) y nuevo (`tutor-core`) conviviendo | Decidir un path; purgar caché y regenerar CSS de Elementor |
| Dashboard de alumno con 200+ queries | `get_course_completed_percent()` por tarjeta | Medir con QM; transient por user+course con invalidación |
| "Tutor ya cachea" pero cada visita pega a la DB | `TutorCache` es solo por request | Object cache persistente (Redis/Memcached/SQLite) + verificar drop-in |
| `wp_options` enorme y TTFB alto | Autoload con opciones grandes; `tutor_option` crece | Medir top 20 por bytes; sacar de autoload lo no-frontend; auditar plugins |
| Emails/tareas de Tutor salen tarde | WP-Cron solo corre con page load y hay page cache | `DISABLE_WP_CRON` + cron del sistema con `wp cron event run --due-now` |
| Importación o exportación muere a los 60 s | `max_execution_time` del plan IONOS | Ejecutar por WP-CLI o por lotes; no subir el límite esperando que el paquete lo permita |
| El carrito muestra datos de otro usuario | Página dinámica cacheada públicamente | Excluir cart/checkout/cuenta/dashboard/learning area; verificar en el edge |
| Botones de quiz o "Continuar" dejan de funcionar tras optimizar | Defer/combine/minify rompió `tutor-core.js` o jQuery | Revertir el defer global; excluir los handles de Tutor; verificar nonces |
| LCP móvil alto en ficha de curso | Hero sin `fetchpriority`, imagen sobredimensionada, CSS de Elementor bloqueando | `fetchpriority=high` en LCP, `srcset` correcto, external CSS medido |
| CLS en ficha de curso | Imagen o player sin dimensiones, fuentes sin `swap` | `width/height` o `aspect-ratio`, `font-display: swap`, `poster` en vídeo |
| El curso no aparece en Google | `noindex` heredado, canonical erróneo, sitemap sin `courses` | Revisar robots del post type, canonical, `sitemap_index.xml` |
| Cambiar la base de curso rompió el blog | Tutor reescribió `permalink_structure` a `/%postname%/` | Restaurar la estructura anotada; 301s; no cambiar bases sin backup |
| Duplicados curso/lección en la SERP | Misma descripción en ficha y lecciones; preview indexable | Contenido distinto por URL; `noindex` o canonical en previews duplicadas |
| El schema no da rich result | Google retiró course-info (sep-2025) | No perseguir rich result; schema solo por claridad semántica, fiel al HTML |
| Compras duplicadas en GA4 | `purchase` en cada recarga de thank-you | Servidor, una vez por pedido, con meta `_ipt_ga4_purchase` |
| Eventos sin datos de ads | Consent Mode mal ordenado o sin v2 | `default` antes de `config/event`; cuatro señales; `wait_for_update` si la CMP es async |

---

## 7. Fuentes

Consultadas el 2026-11-13.

- **Tutor LMS 4.2.1 (código del plugin, wordpress.org):** `classes/Assets.php` (enqueues y `should_load_legacy_scripts`), `classes/Tutor.php` (creación de `tutor_option`, cron `twicedaily`), `classes/Utils.php` (`source_bunnynet`), `models/CourseModel.php` (`get_course_completed_percent`), `cache/TutorCache.php` y `cache/AbstractCache.php`, `classes/Permalink.php`, `classes/Post_types.php` (taxonomías y bloqueo de listados), `classes/Rewrite_Rules.php`, `readme.txt` (requisitos y changelog). https://downloads.wordpress.org/plugin/tutor.zip
- **Google Search Central — changelog:** retirada del tipo "course info" (2025-09-09). https://developers.google.com/search/updates
- **web.dev — Web Vitals:** umbrales LCP/INP/CLS y p75. https://web.dev/articles/vitals
- **web.dev — TTFB:** 0,8 s bueno / 1,8 s pobre. https://web.dev/articles/ttfb
- **WordPress — Options API / `add_option`:** autoload, valores `on|off|auto|auto-on`, cambios en 6.6/6.7. https://developer.wordpress.org/reference/functions/add_option/ · https://developer.wordpress.org/reference/functions/wp_determine_option_autoload_value/ · https://developer.wordpress.org/reference/functions/wp_autoload_values_to_autoload/
- **WordPress — `WP_Object_Cache`:** caché no persistente por defecto; transients a `wp_options` sin backend persistente. https://developer.wordpress.org/reference/classes/wp_object_cache/
- **WordPress — Cron:** WP-Cron se dispara en page load. https://developer.wordpress.org/plugins/cron/
- **Yoast SEO — piezas de schema:** no incluye `Course`. https://developer.yoast.com/features/schema/pieces/
- **Yoast SEO — sitemaps XML:** post types `noindex` excluidos; `X-Robots-Tag: noindex, follow`. https://yoast.com/help/xml-sitemaps-in-the-wordpress-seo-plugin/
- **Yoast — soporte:** "no tiene soporte para `course` schema". https://wordpress.org/support/topic/schema-markup-for-courses/
- **Elementor — Settings/Performance:** CSS Print Method (external file vs inline) y Optimized Image Loading. https://elementor.com/help/settings/
- **WooCommerce — cart fragments:** desde 7.8 solo se encola con el Mini Cart widget; snippet y caveats. https://developer.woocommerce.com/2023/06/16/best-practices-for-the-use-of-the-cart-fragments-api/
- **GA4 — ecommerce:** `purchase` requiere `transaction_id`, `value`, `currency`, `items`. https://developers.google.com/analytics/devguides/collection/ga4/ecommerce
- **Google — Consent Mode v2:** cuatro señales, orden de `default`/`update`, `wait_for_update`. https://developers.google.com/tag-platform/security/guides/consent
- **IONOS — límites PHP:** `memory_limit`, `max_execution_time`, `NPROC`; Performance Levels; ubicación de `php.ini` según contrato. https://www.ionos.com/help/hosting/using-php-for-web-projects/which-php-settings-can-i-change/ · https://www.ionos.com/help/wordpress/wordpress-administration/customizing-wordpress-performance-level/
- **WP-CLI 2.12 (verificado en el entorno):** `wp option list --autoload=on --format=total_bytes`, `wp cron event run --due-now`.
