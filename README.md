# Tutor LMS Expert

Skill para agentes que operan, configuran, diagnostican y prueban **Tutor LMS** en WordPress. Captura el modelo de datos real de Tutor 4, los addons de Tutor Pro, los flujos de creación de cursos y los fallos que aparecen en producción (prerrequisitos que bloquean, progreso falso, certificados que no se emiten, adjuntos que no aparecen, botones rotos, login que termina en un 500).

## Qué incluye

```
tutorlms-expert/
├── SKILL.md                     # instrucciones que carga el agente
├── references/
│   ├── features.md              # core vs addon vs plugin vs motor externo
│   ├── data-model.md            # post types, metas, quizzes, assignments, completion
│   ├── addons.md                # addons de Tutor Pro y cuándo usarlos
│   ├── course-setup.md          # de cero a curso vendible
│   ├── troubleshooting.md       # síntoma → causa → fix
│   ├── qa-student.md            # simulación de estudiante de punta a punta
│   ├── audit-and-migration.md   # auditoría, duplicados y migración de LMS
│   ├── accessibility-compliance.md # WCAG, UPL/EOIR, privacidad y claims
│   ├── commerce-email.md        # WooCommerce, bundles, cupones, emails, DMARC
│   ├── performance-seo.md       # assets, caché, queries, SEO y analytics
│   └── rest-api.md              # /wp-json/tutor/v1: tokens, endpoints, errores
├── scripts/
│   ├── tutor-inspect.sh         # inventario read-only por WP-CLI
│   ├── create-course-structure.php
│   ├── enroll-student.php
│   ├── check-course.php
│   ├── audit-course.php         # auditoría profunda de un curso
│   └── test-skill.sh            # A/B real con opencode (baseline vs skill)
└── evals/evals.json             # 3 casos de prueba realistas
```

## Instalación

Copia la carpeta a tu directorio de skills:

```bash
cp -r tutorlms-expert ~/.agents/skills/
# o
cp -r tutorlms-expert ~/.claude/skills/
```

**Importante (opencode):** el directorio de la skill en `~/.agents/skills/tutorlms-expert` debe ser **real**, no un symlink. opencode no descubre skills cuyo directorio sea un enlace. Si quieres una sola copia, deja el repo ahí y symlinkea la ruta de trabajo:

```bash
mv ~/Documents/GitHub/tutorlms-expert ~/.agents/skills/tutorlms-expert
ln -s ~/.agents/skills/tutorlms-expert ~/Documents/GitHub/tutorlms-expert
opencode debug skill | grep -c '"name": "tutorlms-expert"'   # debe dar 1
```

El agente la cargará cuando detecte Tutor LMS, cursos, lecciones, quizzes, assignments, certificados, bundles, prerrequisitos o problemas de progreso.

## Uso rápido

```bash
# Inventario de un WordPress
./scripts/tutor-inspect.sh /ruta/al/wordpress

# Crear estructura idempotente
wp eval-file scripts/create-course-structure.php structure.json --path=/ruta/al/wordpress

# Crear alumno de QA e inscribirlo
wp eval-file scripts/enroll-student.php 123 qa.student 'change-me-strong-password' qa@example.com 456 --path=/ruta/al/wordpress

# Verificar un curso antes de publicar
wp eval-file scripts/check-course.php 123 --path=/ruta/al/wordpress
```

## Testear la skill

```bash
# Puertas estáticas (segundos, lo mismo que corre el CI)
python3 ~/.agents/skills/skillforge/scripts/validate_skill.py .
python3 ~/.agents/skills/skillforge/scripts/run_skill_evals.py . --static

# A/B real con opencode: baseline en sandbox sin skill vs treatment en el repo
./scripts/test-skill.sh                 # todos los escenarios
./scripts/test-skill.sh rest-auth       # uno solo
REPS=3 ./scripts/test-skill.sh          # varianza de redacción
MODEL=opencode-go/deepseek-v4.1-flash PURE=1 ./scripts/test-skill.sh --judge
```

Los outputs quedan en `/tmp/tutor-evals/`. El baseline corre en un directorio vacío, con `SKILL.md` oculto y prohibido usar tools, para que no pueda leer las referencias. Medido: 37-65 s por brazo, ~3,5 min los dos escenarios.

## Aplicaciones

| Capa | Qué resuelve | Referencia |
|---|---|---|
| **Alcance de features** | Qué es core, addon Pro, plugin aparte o motor externo: eCommerce nativo, subscriptions, memberships, gift course, AI Studio, certificate builder, live classes, content bank, H5P. Evita prometer lo que no existe. | `features.md` |
| **Auditoría y lanzamiento** | Detectar cursos vacíos, lecciones sin enlace, quizzes sin preguntas, tareas sin curso, adjuntos que faltan, certificados sin plantilla, productos sin precio, duplicados y demo huérfano. | `audit-and-migration.md`, `audit-course.php` |
| **Migración** | Traer cursos, lecciones, quizzes y alumnos desde LearnDash, LifterLMS o LearnPress; qué no se migra; rollback. | `audit-and-migration.md` |
| **Soporte y troubleshooting** | Lecciones bloqueadas, progreso falso, “no autorizado”, nonce, certificado que no se emite, botón next, login 500, timezone, CSS global. | `troubleshooting.md` |
| **Comercio y email** | Producto Woo, precio, inscripción, bundles, cupones, suscripciones, SMTP (p. ej. FluentSMTP), DMARC, secuencias de bienvenida/recordatorio/certificado. | `commerce-email.md` |
| **Accesibilidad y cumplimiento** | WCAG 2.2 AA, subtítulos y audio descripción, actividad regulada (legal/salud/finanzas como ejemplos), privacidad, FTC, disclaimers y claims. | `accessibility-compliance.md` |
| **Rendimiento y SEO** | Assets de Tutor, object cache, queries, video, schema de curso, GA4/Meta. | `performance-seo.md` |
| **QA de estudiante** | Simular compra/acceso, recorrer lecciones, quiz, tarea, progreso y certificado. | `qa-student.md` |
| **REST API** | `wp-json/tutor/v1`: login por API key, access/refresh token, endpoints Free vs Pro, por qué 401/403, descubrir rutas. | `rest-api.md` |

## Qué cumple y qué no

- **Accesibilidad:** da criterios y evidencias para WCAG 2.2 AA; no certifica una instalación concreta. Las captions son nivel A y la audio descripción (1.2.5) es el hueco habitual en tutoriales de formularios.
- **Privacidad:** orienta sobre CCPA/CPRA/GDPR y datos sensibles (salud, situación legal, origen étnico, narrativas de caso); no sustituye una revisión legal ni una DPIA.
- **Publicidad y claims:** advierte sobre “oficial”, “garantizado”, “acreditado” y promesas de resultado; no valida claims sin evidencia.
- **Actividad regulada:** la skill da un checklist reutilizable (legal, salud, finanzas); el caso de inmigración es un ejemplo trabajado, no una asunción. Evita prometer credenciales o representación que no existan.
- **Pagos:** asume WooCommerce/Stripe/PayPal y no almacena datos de tarjeta; no sustituye la configuración PCI del gateway.
- **Migración:** cubre las vías oficiales (LearnDash, LifterLMS, LearnPress); Teachable y Moodle no tienen migración oficial y se marcan como no verificables.

## Idioma

- `SKILL.md`, scripts, nombres de archivo y commits: **inglés**.
- `references/`: las nuevas se escriben en inglés. Las referencias antiguas en español se conservan verificadas; no se traducen a medias. Si una se reescribe de fondo, se convierte entera.
- README y respuesta al cliente: español.

## Principios

- **Causa raíz, no parche.** Cada síntoma de la matriz de troubleshooting apunta al dato o la configuración que lo produce.
- **Idempotencia.** Los scripts comprueban existencia por claves estables antes de insertar.
- **No bloquear la venta.** Los prerrequisitos se muestran como recomendación salvo que el cliente pida una compuerta real.
- **No prometer lo que no existe.** Sin video, sin adjunto o sin certificado configurado, se dice; no se rellena con contenido genérico.
- **Verificar como alumno.** Un curso no está listo porque el admin lo vea; está listo cuando un estudiante lo completa y obtiene su certificado.

## Evals

`evals/evals.json` contiene cuatro casos: curso bloqueado por prerrequisito, creación idempotente de estructura, certificado no emitido y alcance de features (qué es nativo). `evals/triggers.json` y `evals/scenarios/` añaden disparadores y escenarios con baseline para SkillForge (`run_skill_evals.py`). `.github/workflows/skill-ci.yml` valida frontmatter, JSON, referencias, PHP, shell y cabeceras sin dependencias externas.

## Licencia

MIT. Ver `LICENSE`.
