---
task: Un cliente reporta 401 en /wp-json/tutor/v1/courses después de renovar la API key, y 403 en /courses/123 con un token de estudiante. Diagnosticar y dar el flujo correcto.
baseline_failure: El baseline suele tratar la API key como Bearer, ignora /auth/login y /auth/refresh, y explica el 403 como permisos de WordPress en lugar de acceso al curso.
assertions:
  - 'Envía Tutor-Api-Key/Tutor-Api-Secret solo a /auth/login y usa Authorization: Bearer para el resto.'
  - 'Explica expires_in, el refresh token y su rotación; no reintenta un refresh ya usado.'
  - 'Distingue 401 (token ausente, inválido o expirado) de 403 (el usuario del token no tiene acceso al curso o la key no tiene Read).'
  - 'Menciona que el contenido requiere curso público, inscripción o rol instructor/admin.'
  - 'Recomienda descubrir rutas con GET /wp-json/tutor/v1 en vez de confiar en las grafías de la doc.'
runs: 1
---

Escenario REST. Requiere `references/rest-api.md`.
