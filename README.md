# Tutor LMS Expert

Skill para agentes que operan, configuran, diagnostican y prueban **Tutor LMS** en WordPress. Captura el modelo de datos real de Tutor 4, los addons de Tutor Pro, los flujos de creación de cursos y los fallos que aparecen en producción (prerrequisitos que bloquean, progreso falso, certificados que no se emiten, adjuntos que no aparecen, botones rotos, login que termina en un 500).

## Qué incluye

```
tutorlms-expert/
├── SKILL.md                     # instrucciones que carga el agente
├── references/
│   ├── data-model.md            # post types, metas, quizzes, assignments, completion
│   ├── addons.md                # addons de Tutor Pro y cuándo usarlos
│   ├── course-setup.md          # de cero a curso vendible
│   ├── troubleshooting.md       # síntoma → causa → fix
│   └── qa-student.md            # simulación de estudiante de punta a punta
├── scripts/
│   ├── tutor-inspect.sh         # inventario read-only por WP-CLI
│   ├── create-course-structure.php
│   ├── enroll-student.php
│   └── check-course.php
└── evals/evals.json             # 3 casos de prueba realistas
```

## Instalación

Copia la carpeta a tu directorio de skills:

```bash
cp -r tutorlms-expert ~/.agents/skills/
# o
cp -r tutorlms-expert ~/.claude/skills/
```

El agente la cargará cuando detecte Tutor LMS, cursos, lecciones, quizzes, assignments, certificados, bundles, prerrequisitos o problemas de progreso.

## Uso rápido

```bash
# Inventario de un WordPress
./scripts/tutor-inspect.sh /ruta/al/wordpress

# Crear estructura idempotente
wp eval-file scripts/create-course-structure.php structure.json --path=/ruta/al/wordpress

# Crear alumno de QA e inscribirlo
wp eval-file scripts/enroll-student.php 1499 qa.student 'QaStudent-IPT-2026!#' qa@example.com 1624 --path=/ruta/al/wordpress

# Verificar un curso antes de publicar
wp eval-file scripts/check-course.php 1499 --path=/ruta/al/wordpress
```

## Principios

- **Causa raíz, no parche.** Cada síntoma de la matriz de troubleshooting apunta al dato o la configuración que lo produce.
- **Idempotencia.** Los scripts comprueban existencia por claves estables antes de insertar.
- **No bloquear la venta.** Los prerrequisitos se muestran como recomendación salvo que el cliente pida una compuerta real.
- **No prometer lo que no existe.** Sin video, sin adjunto o sin certificado configurado, se dice; no se rellena con contenido genérico.
- **Verificar como alumno.** Un curso no está listo porque el admin lo vea; está listo cuando un estudiante lo completa y obtiene su certificado.

## Evals

`evals/evals.json` contiene tres casos: curso bloqueado por prerrequisito, creación idempotente de estructura y certificado no emitido. Se pueden ejecutar con el flujo de `skill-creator` (subagente con la skill vs. baseline, revisión humana y benchmark).

## Licencia

MIT. Ver `LICENSE`.
