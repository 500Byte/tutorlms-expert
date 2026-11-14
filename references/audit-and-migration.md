# Auditoría y migración de cursos Tutor LMS

> Úsalo antes de publicar o vender un curso, y antes de mover contenido desde otro LMS. La auditoría es de solo lectura; la migración no lo es.
>
> **Verificado el 2026-11-13** contra el código de `themeum/tutor` (rama `master`) y la documentación oficial enlazada al final. Lo que no se pudo verificar aparece marcado como **verificar en la instalación**.

---

## 1. Auditoría antes de vender: checklist

Un curso "publicado" puede estar roto de diez formas distintas que la interfaz no muestra. Revisa las doce capas siguientes, en este orden.

### 1.1 Estructura

| Comprobación | Señal de fallo | Severidad |
|---|---|---|
| El curso es un post `courses` existente | ID equivocado o post type distinto | crítica |
| Tiene al menos un `topics` | currículo vacío | crítica |
| Tiene al menos una `lesson` | curso vacío | crítica |
| Cada topic tiene contenido | topic huérfano / sección fantasma | aviso |
| `menu_order` de topics no se repite | orden y botón "siguiente" impredecibles | aviso |
| No hay lecciones/quizzes/assignments con `post_parent` fuera del curso | contenido huérfano | crítica |

El orden dentro de un topic también es `menu_order`. Dos lecciones con el mismo valor rompen la navegación anterior/siguiente aunque el curso se vea bien.

### 1.2 Lecciones sin `_tutor_course_id_for_lesson`

Matiz importante y **verificado en el código actual (rama `master`, 4.x)**: el curso de una lección se resuelve primero por el topic padre (`post_parent`); `_tutor_course_id_for_lesson` es el fallback cuando la lección no tiene topic (`classes/Utils.php`, `get_course_id_by()`). Tutor escribe ese meta al borrar un topic para no perder el contenido.

Consecuencia práctica:

- Lección con topic padre válido y sin meta → funciona, pero algunos addons, informes y consultas SQL que leen el meta la ignoran. Severidad: aviso/info.
- Lección sin topic padre y sin meta → inaccesible. Severidad: crítica.
- Lección con meta apuntando a otro curso → dato obsoleto; corregir o borrar el meta.

No lo des por "roto siempre" ni por "irrelevante siempre": comprueba la cadena `lesson → topic → course`.

### 1.3 Lecciones sin video

`_video` es un array serializado con `source`, `source_video_id`, `source_external_url`, `source_html5`, `poster`.

- Vídeo ausente en todas las lecciones de un curso vendido como formación en vídeo → aviso alto.
- Vídeo ausente en algunas → normal en cursos mixtos; registra cuáles.
- `_video` presente pero con todos los campos vacíos → equivale a sin vídeo.
- `source = html5` exige `source_video_id` o `source_html5`; YouTube/Vimeo exigen `source_video_id`; URL externa exige `source_external_url`.

### 1.4 Quizzes sin preguntas

Un `tutor_quiz` sin filas en `{prefix}tutor_quiz_questions` se muestra, pero no se puede rendir. Es un fallo crítico, no cosmético.

Comprueba además:

- Preguntas de tipo `multiple_choice`, `true_false`, `image_answering`, `matching` u `ordering` con menos de dos respuestas.
- `question_mark` total igual a 0: el `passing_grade` no se puede calcular.
- `passing_grade` vacío o fuera de 0–100.
- Preguntas de otro curso dentro del quiz (copia/pega entre cursos).

### 1.5 Assignments sin `_tutor_course_id_for_assignments`

Sin ese meta, la comprobación de permisos puede fallar y el alumno recibe "No estás autorizado a realizar esta acción". Tutor también resuelve por topic padre, pero el meta es el que consultan las validaciones y los listados de assignments.

Comprueba también `assignment_option`:

- `total_mark` > 0.
- `pass_mark` ≤ `total_mark`.
- `time_duration`, `upload_files_limit`, `upload_file_size_limit` con valores razonables.
- `_tutor_assignment_total_mark` y `_tutor_assignment_pass_mark` coherentes con `assignment_option`.

### 1.6 Attachments

`_tutor_attachments` es un array de IDs de la biblioteca de medios. Un ID borrado deja la pestaña "Archivos adjuntos" vacía o rota.

- Cuenta lecciones con adjuntos.
- Verifica que cada ID exista y siga siendo `attachment`.
- Si la promesa comercial es "descargables", un curso sin ningún adjunto es una promesa vacía.

### 1.7 Certificado

El template se guarda en el meta del curso `tutor_course_certificate_template` (addon Certificate, Pro). Sin template no hay diploma, aunque el curso se complete.

Verifica además:

- Página de certificado configurada en los ajustes de Tutor (si está vacía, los enlaces pueden acabar en `#`).
- Página de verificación configurada.
- `send_certificate_link_to_course_completion_email` si el cliente quiere el enlace en el correo.
- `course_completion_process`: `strict` espera calificación del instructor; `flexible` permite completar al 100 %. Decide esto con el cliente.

**Verificar en la instalación:** el nombre exacto de la opción de la página de certificado cambia entre versiones y el addon es de pago; no está en el repositorio público.

### 1.8 Producto WooCommerce y precio

Hay dos motores y dos almacenamientos distintos. **Verificado en `classes/Course.php`:**

| Motor (`tutor_option.monetize_by`) | Precio | Producto |
|---|---|---|
| `wc` | vive en el producto WooCommerce | `_tutor_course_product_id` |
| `tutor` (nativo, 4.x) | `tutor_course_price` y `tutor_course_sale_price` | sin producto externo |
| `edd` | `edd_price` en el download | `_tutor_course_product_id` |
| `free` | no se vende | — |

Metas de precio verificados en 4.x:

```
_tutor_course_price_type      = free | paid | subscription
tutor_course_price            (sin guion bajo)
tutor_course_sale_price       (sin guion bajo)
tutor_course_selling_option   = one_time | subscription | both | membership | all
_tutor_course_product_id
```

Aviso: instalaciones antiguas pueden conservar `_tutor_course_price` y `_tutor_course_sale_price` con guion bajo. El código actual de eCommerce nativo lee las claves **sin** guion bajo. Audita ambas y trata la clave con guion bajo como dato legacy, no como la fuente de verdad.

Comprobaciones:

- Curso `paid` + `wc`: producto existe, `publish`, precio > 0.
- Curso `paid` + `tutor`: `tutor_course_price` > 0 (o venta > 0).
- Curso `paid` + `monetize_by=free`: nadie puede comprarlo → crítica.
- `selling_option` de suscripción con producto no-suscripción → aviso.
- Precio de venta ≥ precio regular → aviso.

### 1.9 Visibilidad

- `post_status` del curso: `publish` para vender; `draft`, `pending`, `private`, `future` o `trash` no están a la venta.
- `_tutor_is_public_course = yes`: todo el mundo lee las lecciones sin matrícula. Puede ser deseado (lead magnet) o una fuga de contenido.
- `post_password` no vacío: el comprador no puede abrir el curso sin la contraseña.
- Sin imagen destacada y sin categoría: el curso se muestra pobre en listados y filtros.

### 1.10 Duplicados

- Cursos con el mismo `post_title` (el buscador y el editor confunden al operador; Woo puede duplicar productos).
- Lecciones con el mismo título dentro del curso (el progreso y los reportes se leen mal).
- Temas duplicados por copia/pega.

### 1.11 Demo huérfano

Dos casos distintos:

1. Curso de demostración/ejemplo que quedó publicado: título con `demo`, `sample`, `ejemplo`, `prueba` o `test`, sin matrículas completadas.
2. Contenido que apunta al curso por meta pero ya no cuelga de ninguno de sus topics. Es el "huérfano" real: aparece en consultas, no en el currículo.

No borres por título sin comprobar matrículas, pedidos ni enlaces entrantes.

### 1.12 Zona horaria y emails

- `timezone_string` vacío: WordPress usa un offset fijo y los deadlines, drip y fechas de certificado se desplazan con el horario de verano. En un curso con cohortes es un fallo real.
- `email_from_address` vacío o inválido: los correos de Tutor salen con el remitente por defecto del hosting y acaban en spam. Verifica SMTP antes de vender.
- Decide si el correo de finalización incluye el enlace al certificado.

---

## 2. Detección con SQL/WP-CLI

Sustituye `{prefix}` por `$wpdb->prefix` (puede no llevar guion bajo). Todo lo siguiente es lectura.

### 2.1 Curso vacío o a medio construir

```sql
SELECT c.ID,
       c.post_title,
       c.post_status,
       COUNT(DISTINCT t.ID) AS topics,
       COUNT(DISTINCT l.ID) AS lessons,
       COUNT(DISTINCT q.ID) AS quizzes,
       COUNT(DISTINCT a.ID) AS assignments
FROM {prefix}posts c
LEFT JOIN {prefix}posts t ON t.post_parent = c.ID AND t.post_type = 'topics'
LEFT JOIN {prefix}posts l ON l.post_parent = t.ID AND l.post_type = 'lesson'
LEFT JOIN {prefix}posts q ON q.post_parent = t.ID AND q.post_type = 'tutor_quiz'
LEFT JOIN {prefix}posts a ON a.post_parent = t.ID AND a.post_type = 'tutor_assignments'
WHERE c.post_type = 'courses'
GROUP BY c.ID, c.post_title, c.post_status
HAVING lessons = 0 OR topics = 0
ORDER BY c.ID;
```

Un curso con `topics > 0` y `lessons = 0` es un cascarón: se puede publicar y no tiene contenido.

### 2.2 Topics vacíos

```sql
SELECT t.ID, t.post_title, t.post_parent AS course_id
FROM {prefix}posts t
WHERE t.post_type = 'topics'
  AND NOT EXISTS (
    SELECT 1 FROM {prefix}posts c
    WHERE c.post_parent = t.ID
      AND c.post_type IN ('lesson', 'tutor_quiz', 'tutor_assignments')
  );
```

### 2.3 Lecciones sin enlace al curso

```sql
SELECT l.ID, l.post_title, l.post_parent AS topic_id
FROM {prefix}posts l
LEFT JOIN {prefix}postmeta pm
       ON pm.post_id = l.ID AND pm.meta_key = '_tutor_course_id_for_lesson'
WHERE l.post_type = 'lesson'
  AND (pm.meta_value IS NULL OR pm.meta_value = '');
```

Separa después las que sí tienen topic padre de las que no. Solo las segundas están realmente rotas.

### 2.4 Contenido que apunta al curso pero no cuelga de él

```sql
SELECT pm.post_id AS lesson_id
FROM {prefix}postmeta pm
JOIN {prefix}posts l ON l.ID = pm.post_id AND l.post_type = 'lesson'
WHERE pm.meta_key = '_tutor_course_id_for_lesson'
  AND pm.meta_value = <course_id>
  AND l.post_parent NOT IN (
    SELECT ID FROM {prefix}posts WHERE post_parent = <course_id> AND post_type = 'topics'
  );
```

Equivalente para assignments con `_tutor_course_id_for_assignments`.

### 2.5 Lecciones sin vídeo

```sql
SELECT l.ID, l.post_title
FROM {prefix}posts l
LEFT JOIN {prefix}postmeta pm
       ON pm.post_id = l.ID AND pm.meta_key = '_video'
WHERE l.post_type = 'lesson'
  AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = 'a:0:{}');
```

`_video` puede existir con todos los campos vacíos; para eso usa el script, que valida `source` y el ID/URL.

### 2.6 Quizzes sin preguntas

```sql
SELECT q.ID, q.post_title, COUNT(qq.question_id) AS questions
FROM {prefix}posts q
LEFT JOIN {prefix}tutor_quiz_questions qq ON qq.quiz_id = q.ID
WHERE q.post_type = 'tutor_quiz'
GROUP BY q.ID, q.post_title
HAVING questions = 0;
```

Preguntas sin respuestas (tipos que las exigen):

```sql
SELECT qq.question_id, qq.quiz_id, qq.question_type, COUNT(a.answer_id) AS answers
FROM {prefix}tutor_quiz_questions qq
LEFT JOIN {prefix}tutor_quiz_question_answers a
       ON a.belongs_question_id = qq.question_id
WHERE qq.question_type IN ('multiple_choice','true_false','image_answering','matching','ordering')
GROUP BY qq.question_id, qq.quiz_id, qq.question_type
HAVING answers < 2;
```

### 2.7 Assignments sin curso

```sql
SELECT a.ID, a.post_title, a.post_parent AS topic_id
FROM {prefix}posts a
LEFT JOIN {prefix}postmeta pm
       ON pm.post_id = a.ID AND pm.meta_key = '_tutor_course_id_for_assignments'
WHERE a.post_type = 'tutor_assignments'
  AND (pm.meta_value IS NULL OR pm.meta_value = '');
```

### 2.8 Adjuntos rotos

WP-CLI no valida IDs dentro de un array serializado. El script los recorre. En SQL puro tendrías que deserializar; usa el script.

### 2.9 Duplicados por título

```sql
SELECT post_title, COUNT(*) AS total, GROUP_CONCAT(ID ORDER BY ID) AS ids
FROM {prefix}posts
WHERE post_type = 'courses' AND post_status <> 'trash'
GROUP BY post_title
HAVING total > 1;
```

### 2.10 Demo huérfano

```sql
SELECT c.ID, c.post_title, c.post_status
FROM {prefix}posts c
WHERE c.post_type = 'courses'
  AND c.post_status = 'publish'
  AND (LOWER(c.post_title) LIKE '%demo%'
       OR LOWER(c.post_title) LIKE '%sample%'
       OR LOWER(c.post_title) LIKE '%ejemplo%'
       OR LOWER(c.post_title) LIKE '%prueba%')
  AND NOT EXISTS (
    SELECT 1 FROM {prefix}posts e
    WHERE e.post_type = 'tutor_enrolled'
      AND e.post_parent = c.ID
      AND e.post_status = 'completed'
  );
```

### 2.11 Script de auditoría completo

`scripts/audit-course.php` ejecuta todas las comprobaciones anteriores en una sola pasada, con severidad y salida legible o JSON:

```bash
wp eval-file scripts/audit-course.php 123
wp eval-file scripts/audit-course.php 123 --format=json
wp eval-file scripts/audit-course.php 123 --fail-on=critical   # sale con código 2 si hay críticos
```

Es de solo lectura e idempotente: no escribe posts, metas, opciones ni user meta.

### 2.12 WP-CLI: lo que existe y lo que no

Tutor LMS **no publica un namespace propio de WP-CLI** (`wp tutor ...`). La documentación oficial de WP-CLI no lista comandos de Tutor y el plugin no los registra. Todo se hace con:

- `wp eval` / `wp eval-file` para lógica PHP.
- `wp post list`, `wp post meta get`, `wp db query` para inspección.
- `wp option get tutor_option --format=json` para la configuración.

No inventes `wp tutor course audit`; no existe.

---

## 3. Migración desde otro LMS

### 3.1 Matriz de soporte oficial

| Origen | Vía | Requisitos | ¿Oficial? |
|---|---|---|---|
| LearnDash | Tutor LMS Migration Tool | LearnDash 4.0+, Tutor 4.0+, Migration Tool 2.6.0 | sí |
| LifterLMS | Tutor LMS Migration Tool | LifterLMS 8.0+, Tutor 4.0+ | sí |
| LearnPress | Tutor LMS Migration Tool | LearnPress 4.0+, Tutor 4.0+ | sí |
| WooCommerce (solo eCommerce) | Migration Tool → Native eCommerce | WooCommerce 10.0+ | sí |
| Tutor → Tutor (otra instalación) | Tutor Pro → Tools → Import/Export (JSON) | Tutor Pro | sí |
| Teachable | — | — | **no** |
| Moodle | — | — | **no** |

**Lo que no tiene vía oficial no se resuelve con un plugin mágico.** Para Teachable o Moodle el trabajo es: exportar contenido (CSV, HTML, API o backup), reconstruir cursos/topics/lecciones con un importador propio, y migrar alumnos/progreso con un mapeo explícito. Presupuesta ese trabajo como contenido, no como configuración.

### 3.2 Qué se puede migrar

Con Migration Tool desde LearnDash/LifterLMS/LearnPress:

- Cursos, topics, lecciones.
- Quizzes, preguntas, respuestas y attempts.
- Assignments y envíos.
- Alumnos, instructores, matrículas y estado de finalización.
- Progreso de lecciones y quizzes.
- Reviews (según origen).
- Ventas, pedidos, suscripciones y cupones (según motor de eCommerce).
- Taxonomías, medios enlazados en lecciones.

Con Import/Export de Tutor Pro:

- Cursos y bundles seleccionados.
- Lecciones, quizzes, assignments y medios.
- Ajustes globales de Tutor (¡ojo: sobrescriben los del destino!).

### 3.3 Qué no se puede migrar (o no es fiable)

- **Hashes de certificados y enlaces de verificación.** Se regeneran; los enlaces históricos dejan de ser válidos.
- **Plantillas de certificado y su diseño.** Se rehacen en Certificate Builder.
- **Ajustes de addons y flags por curso** (drip, prerequisites, bundle composition). Hay que reconfigurarlos.
- **Contenido construido con page builders dentro de lecciones.** El HTML/shortcode puede sobrevivir; el layout no se reconstruye.
- **H5P, clases en vivo (Zoom/Meet), integraciones externas.** Requieren reinstalar y reconectar cuentas.
- **Tipos de pregunta sin equivalente** en el destino. LearnDash/LifterLMS convierten algunos tipos; Moodle y Teachable no tienen conversión oficial.
- **Histórico analítico y reportes.** Empiezan de cero en el destino.
- **Permisos y roles finos** (co-instructores, capacidades personalizadas).
- **Enlaces internos y GUID.** Un search-replace no debe tocar GUID.

**Verificar en la instalación:** la lista exacta de tipos de pregunta que convierte cada versión del Migration Tool, y si conserva el `attempt` histórico con sus marcas. La documentación lo anuncia, pero no detalla el mapeo pregunta a pregunta.

### 3.4 Orden seguro

1. **Congela el contenido.** Nadie edita cursos durante la migración.
2. **Inventario en origen.** Cuenta cursos, topics, lecciones, quizzes, preguntas, assignments, matrículas, finalizaciones, pedidos y suscripciones. Guarda el número; será tu test.
3. **Backup completo** de origen y destino: base de datos, `wp-content/uploads`, lista de plugins/versiones.
4. **Staging del destino.** Nunca pruebes la migración en producción.
5. **Instala y configura el destino antes de migrar**: Tutor 4.x, Tutor Pro, addons necesarios (assignments, certificate, subscriptions) y el motor de eCommerce correcto. Si el addon no está activo, esos datos no se migran o no se pueden gestionar.
6. **Configura zona horaria, moneda, país y emails** antes de ejecutar. Los pedidos y las fechas se escriben con esa configuración.
7. **Ejecuta la migración en staging.** Anota duración y errores.
8. **Verifica conteos** contra el inventario del paso 2. Si un número no cuadra, para.
9. **Revisa 3 cursos completos** a mano: estructura, vídeo, adjuntos, quiz, assignment, certificado, producto y compra.
10. **Prueba el search-replace al dominio final** en staging.
11. **Producción:** modo mantenimiento → backup → migración → verificación → cutover.
12. **No desactives el LMS de origen** hasta cerrar la ventana de verificación. En LearnDash, la propia herramienta avisa de que los datos migrados dejan de ser accesibles en LearnDash al terminar.

### 3.5 IDs y search-replace

- La Migration Tool reescribe las relaciones (`post_parent`, metas de curso) al crear los nuevos posts. **No mapees IDs a mano** ni copies filas de `wp_posts` entre sitios.
- Si clonas el sitio o cambias de dominio, usa WP-CLI, no SQL crudo:

```bash
wp search-replace 'https://staging.example.com' 'https://example.com' \
  --all-tables --precise --recurse-objects \
  --skip-columns=guid
```

- `--precise` y `--recurse-objects` son los que evitan romper arrays serializados. Un `UPDATE ... REPLACE()` sobre `postmeta` puede corromper `_video`, `_tutor_attachments`, `tutor_quiz_option` y `assignment_option`.
- **No reemplaces la columna `guid`.** WordPress la usa como identificador, no como URL viva.
- Después: `wp cache flush`, `wp rewrite flush`, y revisa `home`, `siteurl` y las URLs dentro de `tutor_option`.
- Vuelve a pasar el script de auditoría al curso migrado.

### 3.6 Verificación post-migración

| Qué | Cómo |
|---|---|
| Conteos | comparar con el inventario del paso 2 |
| Estructura | `topics → lessons/quizzes/assignments` por curso |
| Metas | `_tutor_course_id_for_lesson`, `_tutor_course_id_for_assignments` |
| Medios | `_video` reproduce; `_tutor_attachments` apunta a attachments existentes |
| Quizzes | preguntas y respuestas conservadas; `passing_grade` |
| Assignments | `assignment_option` con notas |
| Matrículas | `tutor_enrolled` con estado `completed` |
| Progreso | lección completada y curso completado |
| Certificado | emitido con el template nuevo |
| Compra | producto Woo/precio nativo y checkout de prueba |

---

## 4. Plan de rollback

La migración de LearnDash **destruye el acceso a los datos migrados en el origen**. Por tanto el rollback no es "deshacer la migración": es **restaurar el backup**. Prepáralo antes de tocar nada.

### 4.1 Artefactos previos (obligatorios)

- `wp db export` completo, con fecha en el nombre.
- `tar` de `wp-content/uploads`.
- Lista de plugins y versiones (`wp plugin list --format=csv`).
- `wp option get tutor_option --format=json` y `wp option get tutor_addons_config --format=json`.
- Inventario de conteos del origen.
- IDs de productos WooCommerce y de pedidos si hay ventas.
- Copia de los ajustes de email/SMTP y de la zona horaria.

### 4.2 Procedimiento

1. Activa modo mantenimiento.
2. Restaura la base de datos desde el dump previo.
3. Restaura `wp-content/uploads` si la migración movió medios.
4. Restaura versiones de plugins si se actualizaron para migrar.
5. `wp cache flush` y `wp rewrite flush`.
6. Verifica conteos y abre 3 cursos.
7. Desactiva mantenimiento.

### 4.3 Fallo a mitad de migración

No reintentes a ciegas sobre la base de datos parcial. Restaura, corrige la causa en staging (addon faltante, motor de eCommerce equivocado, versión de origen no soportada) y repite el ciclo completo. Un reintento sobre datos parciales duplica cursos y matrículas.

---

## 5. Errores comunes

1. **Migrar en producción primero.** La herramienta no es idempotente a nivel de contenido: repetir crea duplicados.
2. **No activar los addons antes.** Assignments, certificate y subscriptions deben existir antes de migrar o esos datos se pierden o quedan inmanejables.
3. **Motor de eCommerce equivocado.** Los pedidos van al motor activo en el momento de migrar. Configúralo antes.
4. **Search-replace con SQL crudo.** Rompe `_video`, `_tutor_attachments` y las opciones serializadas de quiz/assignment.
5. **Reemplazar GUID.** Rompe referencias internas.
6. **Asumir `_tutor_course_price`.** En 4.x nativo la clave es `tutor_course_price`, sin guion bajo. Audita ambas.
7. **Asumir que `_tutor_course_id_for_lesson` siempre hace falta.** Es fallback; el topic padre manda. Pero el contenido sin topic ni meta sí está roto.
8. **Ignorar `timezone_string`.** Fechas y deadlines se desplazan con DST.
9. **Certificado con template pero sin página de certificado.** El enlace acaba en `#`.
10. **Importar el JSON de ajustes sobre el destino.** Sobrescribe la configuración actual; importa contenido, no ajustes, salvo que sea intencional.
11. **Esperar migración de Teachable o Moodle.** No existe vía oficial; es reconstrucción.
12. **Desactivar el LMS de origen demasiado pronto.** Cierra la ventana de verificación y deja el rollback dependiendo solo del backup.

---

## 6. Señal → consulta → decisión

| Señal | Consulta / comprobación | Decisión |
|---|---|---|
| Curso sin topics o sin lecciones | §2.1 | No publicar. Completar currículo o despublicar. |
| Topic sin contenido | §2.2 | Añadir contenido, fusionar o borrar el topic. |
| Lección sin `_tutor_course_id_for_lesson` | §2.3 + `post_parent` | Si tiene topic padre: aviso, no bloquea. Si no: reparar meta o recolgar la lección. |
| Lección que apunta al curso pero está fuera de sus topics | §2.4 | Recolgar bajo un topic o corregir el meta. |
| Lección sin vídeo | §2.5 | Si el curso es en vídeo: grabar/subir. Si es mixto: documentar. |
| Quiz sin preguntas | §2.6 | Bloqueante: añadir preguntas o despublicar el quiz. |
| Pregunta sin respuestas | §2.6 | Bloqueante para ese tipo: añadir opciones correctas. |
| Quiz sin marcas o `passing_grade` inválido | `question_mark`, `tutor_quiz_option` | Corregir antes de vender. |
| Assignment sin curso | §2.7 | Reparar meta; verificar que el alumno puede abrirlo. |
| Adjunto inexistente | script | Re-subir el archivo o quitar el ID. |
| Sin certificado | `tutor_course_certificate_template` | Asignar template o retirar la promesa de diploma. |
| Curso `paid` sin producto/precio | §1.8 | Bloqueante: crear/vincular producto o poner precio nativo. |
| Curso `paid` con `monetize_by=free` | `tutor_option` | Bloqueante: activar motor de venta. |
| Producto no publicado o precio 0 | WooCommerce | Bloqueante: publicar y poner precio. |
| Curso no `publish` | `post_status` | Decidir: publicar o mantener fuera de venta. |
| Curso con contraseña | `post_password` | Quitar la contraseña o entregarla al comprador. |
| Títulos de curso duplicados | §2.9 | Fusionar, renombrar o borrar el duplicado. |
| Curso demo con 0 matrículas | §2.10 | Borrar o convertir en plantilla interna. |
| `timezone_string` vacío | `wp option get timezone_string` | Configurar zona IANA (`America/New_York`, etc.). |
| `email_from_address` vacío/inválido | `tutor_option` | Configurar remitente real + SMTP antes de vender. |
| Migración con conteos distintos al inventario | §3.6 | Parar, restaurar y repetir en staging. |

---

## 7. Fuentes

Consultadas el 2026-11-13:

- Tutor LMS — Migration Tool overview: <https://tutorlms.com/docs/migration/migration-tool-overview/>
- Tutor LMS — LearnDash migration (datos migrados, requisitos, aviso destructivo): <https://tutorlms.com/docs/migration/learndash-to-tutor-lms-migration/>
- Tutor LMS — LifterLMS migration: <https://tutorlms.com/docs/migration/lifterlms-to-tutor-lms-migration/>
- Tutor LMS — Import/Export de cursos (JSON, ajustes, medios): <https://tutorlms.com/docs/tutorials/course-import-export-tutorial/>
- Tutor LMS — Tools (Import/Export, Settings Log, WooCommerce Migration): <https://tutorlms.com/docs/menu/tools/>
- Tutor LMS — Course Builder / Curriculum: <https://tutorlms.com/docs/course-builder/curriculum/>
- Tutor LMS — Quiz Builder y tipos de pregunta: <https://tutorlms.com/docs/quiz-builder/quiz-builder/>
- Tutor LMS — Certificate addon: <https://tutorlms.com/docs/addons/certificate-addon/>
- Tutor LMS — Course Attachments: <https://tutorlms.com/docs/addons/course-attachments/>
- Tutor LMS — System requirements (PHP/WordPress/MySQL): <https://tutorlms.com/docs/getting-started/system-requirements/>
- Migration Tool en WordPress.org (versiones soportadas y datos por origen): <https://wordpress.org/plugins/tutor-lms-migration-tool/>
- Código de Tutor LMS (post types, precio, curso por lección, quiz schema): <https://github.com/themeum/tutor>
  - `classes/Course.php`: constantes de precio/venta y `COURSE_PRODUCT_ID_META`.
  - `classes/Utils.php`: `get_course_id_by()`, `get_course_id_by_lesson()`, `get_raw_course_price()`.
  - `classes/Tutor.php`: `CREATE TABLE` de `tutor_quiz_questions` y `tutor_quiz_question_answers`.
  - `models/EnrollmentModel.php`: estados `completed`, `pending`, `cancel` y metas de pedido.
- WP-CLI — comandos disponibles (no hay namespace de Tutor): <https://developer.wordpress.org/cli/commands/>
- WP-CLI — `search-replace`: <https://developer.wordpress.org/cli/commands/search-replace/>
- Referencia de tablas de Tutor LMS 4.1 (tercero, contrastada con el código): <https://allinonelms.com/blog/tutor-lms-database-tables>
- Certificados y meta `tutor_course_certificate_template` (tercero, Pro): <https://tutorlmsexpert.com/knowledge-base/tutor-lms-pro/tutor-lms-certificates/>

---

## 8. Lo que no se pudo verificar

- El nombre exacto de la opción de **página de certificado** en `tutor_option` para 4.1.x; el addon es de pago y no está en el repositorio público. El script no lo comprueba.
- El mapeo **pregunta a pregunta** de cada tipo entre LearnDash/LifterLMS y Tutor, y si los attempts históricos conservan todas sus marcas.
- Si el Migration Tool migra **co-instructores** y permisos finos, o solo el autor principal.
- El comportamiento exacto de la migración **Teachable → Tutor** y **Moodle → Tutor**: no hay documentación oficial; se asume reconstrucción.
- Si una instalación concreta conserva `_tutor_course_price` legacy y si el upgrade a 4.x la convierte automáticamente a `tutor_course_price`. Audita ambas claves en la instalación real.
- La validez de `send_certificate_link_to_course_completion_email` y `email_from_address` en la versión instalada (aparecen en la documentación del proyecto, no en el repositorio público de la versión libre).
