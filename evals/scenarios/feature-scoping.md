---
task: Scopear una academia de pago en Tutor LMS (cursos, bundle, suscripción, gift, memberships, live classes, IA, certificados, gradebook, content bank, multi-instructor, H5P) diciendo para cada punto si es core, addon Pro o plugin externo, su nombre en el admin y de qué depende.
baseline_failure: El baseline afirmó que gift course, memberships, subscriptions, AI Studio, calendario, Content Bank y H5P "no son nativos", propuso WooCommerce Subscriptions como única vía recurrente y recomendó un plugin externo de gift cards. También omitió el eCommerce nativo y la exclusividad de motor.
assertions:
  - Identifica Native eCommerce en Settings > Monetization y que un solo motor por sitio es obligatorio.
  - Clasifica Subscriptions y Memberships como addon Pro que exige motor Native, con planes full-site o por categoría.
  - Clasifica Gift Course como toggle core en Settings > Course y aclara que el reembolso solo aplica antes de Reveal Gift.
  - Ubica AI Studio en el Course Builder con un conector de IA y Content Bank como addon Pro de contenido reutilizable.
  - Clasifica Certificate Builder como plugin separado más addon Certificate.
  - Exige addon Zoom o Google Meet para Live Classes.
  - Reconoce Gradebook, Multi Instructors y H5P como addons Pro.
  - No afirma que ninguna de esas features "no sea nativa"; lista como no nativo solo cohorts, SCORM, afiliados, SMS, CRM o weighting por curso.
  - Advierte que un toggle no equivale a feature funcionando (credenciales, plan, plantilla, contenido).
runs: 1
---

Escenario de alcance. El agente debe leer `references/features.md` antes de responder. Se evalúa contra el baseline registrado el 2026-11-13, no contra la documentación.
