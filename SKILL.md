---
name: tutorlms-expert
description: Operate, configure, troubleshoot, and QA Tutor LMS (TutorLMS) on WordPress. Use whenever the user mentions Tutor LMS, courses, lessons, topics, quizzes, assignments, certificates, enrollments, student dashboard, course bundles, memberships, subscriptions, gift courses, native ecommerce, AI Studio, live classes, prerequisites, content drip, gradebook, or any Tutor LMS problem (lessons blocked by prerequisites, progress not completing, certificate not issued, missing attachments, broken next button, login redirect, false progress). Also use when scoping which Tutor feature to use, creating or auditing a course structure, configuring Tutor Pro addons, integrating the REST API (tokens, 401/403), or simulating a student end-to-end.
license: MIT
metadata:
  version: "2026.11.13"
  docs_verified: "2026-11-13"
  compatibility: WordPress with Tutor LMS 3.x/4.x, Tutor Pro, WP-CLI or SSH access, optionally an MCP/REST client. REST token auth requires Tutor LMS 4.2+. Some features need WooCommerce, a separate plugin, or external credentials (Zoom, Meet, AI key).
  obsolescence: Re-check the official docs at every Tutor minor release; menus, tiers and token auth change between 3.x, 4.0-4.1 and 4.2+.
---

# Tutor LMS Expert

Tutor LMS is deceptively stateful: a course is posts plus enrollment records, completion comments, user meta, addon flags and commerce products. Most "Tutor bugs" are two of those disagreeing.

## The model you must hold in your head

Tutor 4 stores the curriculum as WordPress posts, nested with `post_parent` + `menu_order`:

| Piece | Post type | Parent | Key meta |
|---|---|---|---|
| Course | `courses` | 0 | `tutor_course_price`, `tutor_course_sale_price` (legacy `_tutor_course_price`), `_tutor_course_product_id`, `_tutor_is_public_course`, `_course_duration`, `_tutor_course_prerequisites_ids`, `tutor_course_certificate_template` |
| Topic | `topics` | course ID | `menu_order` |
| Lesson | `lesson` | topic ID | `_tutor_course_id_for_lesson` (fallback), `_video`, `_tutor_attachments`, `_is_preview` |
| Quiz | `tutor_quiz` | topic ID | `tutor_quiz_option`; questions/answers in `{prefix}tutor_quiz_questions` / `{prefix}tutor_quiz_question_answers` |
| Assignment | `tutor_assignments` | topic ID | `_tutor_course_id_for_assignments`, `assignment_option`, `_tutor_assignment_total_mark`, `_tutor_assignment_pass_mark` |
| Enrollment | `tutor_enrolled` | course ID | `post_author` = student; `_tutor_enrolled_by_order_id`, `_tutor_enrolled_by_product_id` |
| Announcement | `tutor_announcements` | course ID | none needed |
| Bundle | `course-bundle` | 0 | `bundle-course-ids` = `"12,34,56"`; price derived from courses, no auto discount |

Completion is a comment: `comment_agent = 'TutorLMSPlugin'`, `comment_type = 'course_completed'`, `comment_post_ID = course_id`, `user_id = student_id`. Lesson completion is user meta `_tutor_completed_lesson_id_<lesson_id>`.

Full inventory: `references/data-model.md`.

## Feature surface

Tutor ships core, Pro addons, separate plugins and external engines. Calling a feature "not native" is the most expensive scoping error here.

| Claim | Reality |
|---|---|
| Native eCommerce is core | `Settings > Monetization`, 11 gateways, coupons, taxes, orders; switching engine requires regenerating Tutor pages |
| Subscriptions / Memberships need Woo | No: Pro addon on the **Native engine**; a Woo site can use the Woo Subscriptions addon instead of migrating engines. Memberships is full-site or category, with trials and Membership-Only Mode |
| Gift course needs a plugin | No: core toggle in `Settings > Course`; gifts courses and bundles; refunds stop at "Reveal Gift" |
| AI Studio and Content Bank are external | AI Studio is in the Course Builder (needs an AI key); Content Bank is a Pro addon for reusable lessons/assignments/questions |
| Certificates are one addon | Certificate Builder is a **separate plugin** plus the Certificate addon |
| Live classes are built in | Learner screen is core but **requires Zoom or Google Meet addon** and credentials |
| Bundles/Assignments/Gradebook/H5P/Calendar/Multi-instructors | All Pro addons, each with its own dependency |
| Cohorts, SCORM, affiliate, SMS, CRM, per-course weighting | Not documented Tutor features; scope as custom work |

Admin paths, tiers and gotchas: `references/features.md`.

## Process

1. **Inspect.** Course ID, curriculum, enrollments, addon flags, `tutor_option` keys. Use `scripts/tutor-inspect.sh`.
2. **Plan the root-cause change.** Content, configuration, template or commerce — never CSS or a hardcoded `if` on the symptom.
3. **Apply idempotently.** Check existence by stable keys (course + title, quiz + question, course + user); re-running must not duplicate.
4. **Verify with data.** Re-read posts/meta/options and run the SQL. For student-facing changes, run `references/qa-student.md`.
5. **Record change and rollback.** Keep the before-image (meta, option value, revision ID).

## Safety rules

- **Back up before bulk or destructive work** (`wp db export`, at least `posts` + `postmeta`).
- **Never call `update-page-settings` on the Elementor kit**; it wipes the design system. Use read-modify-write `wp eval`.
- **Never write serialized meta blind.** Use `update_post_meta()` in PHP; `maybe_serialize`/`wp_slash` matter.
- **Do not block the sale to enforce pedagogy.** Prerequisites are recommendations unless the client wants a gate.
- **Do not mark progress** for content behind a redirect, prerequisite or permission wall.
- **Do not promise a certificate** the configuration cannot issue; decide `course_completion_process` with the client.
- **Do not invent content, credentials or features.** If the install lacks the addon, say so.
- **One commerce engine per site.** Mixing Native and WooCommerce breaks enrollment mapping.

## Jobs

| Job | Do this | Reference |
|---|---|---|
| Create or audit a structure | `create-course-structure.php` + `check-course.php`; idempotent by title + parent | `course-setup.md` |
| Media and attachments | `_video` array + `_tutor_attachments` IDs; large video on YouTube/Vimeo/Bunny | `course-setup.md` |
| Quizzes | `tutor_quiz_option` + questions/answers tables; verify passing grade and submit | `data-model.md` |
| Assignments and gradebook | course link + `assignment_option` + enrollment; scale is global | `course-setup.md` |
| Certificates | template + `course_completion_process`; verify with a real student | `course-setup.md` |
| Prerequisites and drip | recommendation by default; drip for cohorts | `addons.md` |
| Bundles, subscriptions, memberships | Native engine + product/plan; draft until pricing is approved | `commerce-email.md`, `features.md` |
| Gift course | `Settings > Course` toggle; reveal = enrollment | `features.md` |
| Login, dashboard, redirects | `login_redirect` for non-admins; populate the dashboard page | `troubleshooting.md` |
| REST integration | login → Bearer → refresh; token auth needs Tutor 4.2+; discover routes instead of trusting doc spellings | `rest-api.md` |

## Troubleshooting matrix

| Symptom | Likely cause | Fix |
|---|---|---|
| Lesson/quiz/task 302 → `#tutor_prereq` | `_tutor_course_prerequisites_ids` requires an incomplete course | Remove the prerequisite or enroll the student; no silent redirect |
| Progress rises without showing the lesson | Auto-complete fires before the prerequisite redirect | Gate completion on real lesson access |
| "No estás autorizado" on an assignment | Student not enrolled in its course | Enroll; verify `_tutor_course_id_for_assignments` |
| Certificate missing at 100% | `strict` mode and an assignment is `Pending` | Grade it or switch to `flexible` |
| "Archivos adjuntos" tab empty | `_tutor_attachments` missing | Attach the ID array to the lesson |
| Next button does nothing | `next_id = 0` or `next_is_locked` | Check `get_course_prev_next_contents_by_id()`; fix order/visibility |
| Buttons stretch full width | Global CSS on `button`/`.tutor-btn-icon` | Remove the global rule; scope the fix |
| Login ends in `wp-admin/profile.php` 500 | No student dashboard redirect | `login_redirect` filter; populate the dashboard |
| REST 401/403 | Token expired, key sent as Bearer, or user lacks access | Refresh; keys only at `/auth/login`; fix enrollment/publication |

More detail and exact SQL: `references/troubleshooting.md`.

## Verification

Before declaring done:

- [ ] Re-read the changed posts/meta/options and pasted the output.
- [ ] Counts match the intended structure (topics, lessons, quizzes, enrollments).
- [ ] A real or simulated student can reach and complete the changed content.
- [ ] No 401/403/302 left in the path, and no false progress.
- [ ] Rollback documented (before-image or reverse operation).

## Anti-Patterns

- Patching a blocked lesson or fake progress with CSS, `try/catch` or a hardcoded ID.
- Writing serialized meta with raw SQL or `UPDATE ... REPLACE()`.
- Turning on a hard prerequisite gate on a paid course without the client's explicit decision.
- Promising certificates, live classes or AI output that the install cannot produce.
- Duplicating content by hand when Content Bank or idempotent importers exist.

## Scripts

- `scripts/tutor-inspect.sh` — inventory courses, curriculum counts, addons, options, enrollments (read-only).
- `scripts/create-course-structure.php` — idempotent topic/lesson importer from JSON.
- `scripts/enroll-student.php` — create a student and enroll them (idempotent).
- `scripts/check-course.php` — verify counts, attachments, videos, prerequisites, completion.
- `scripts/audit-course.php` — deep audit: missing links, empty quizzes, orphans, product/price, duplicates.

All accept `--path=<wordpress-root>` and print a paste-ready report.

## References

- `features.md` — core vs Pro addon vs separate plugin vs external engine, admin paths, dependencies, gotchas.
- `data-model.md` — tables, post types, meta keys, quiz/assignment schemas, completion.
- `addons.md` — Pro addons, what each does, how to enable and check them.
- `course-setup.md` — zero to sellable course.
- `troubleshooting.md` — symptom → cause → fix, with SQL/WP-CLI.
- `qa-student.md` — end-to-end student QA.
- `audit-and-migration.md` — audit, duplicates, migration from other LMS.
- `accessibility-compliance.md` — WCAG 2.2 AA, captions, regulated advice, privacy, claims.
- `commerce-email.md` — WooCommerce states, bundles, coupons, subscriptions, SMTP, DMARC.
- `performance-seo.md` — assets, cache, slow queries, video, SEO, analytics.
- `rest-api.md` — `/wp-json/tutor/v1`: tokens, endpoints, access rules, errors.

## Which reference to read

| Question | Reference |
|---|---|
| "Does Tutor do X? Core, addon or custom?" | `features.md` |
| "What post/meta/table is this?" | `data-model.md` |
| "How do I build a course from zero?" | `course-setup.md` |
| "Blocked lesson / wrong progress / no certificate?" | `troubleshooting.md` |
| "Can a student complete the flow?" | `qa-student.md` |
| "Ready to sell? Can I migrate it?" | `audit-and-migration.md` |
| "Accessible and legal to sell?" | `accessibility-compliance.md` |
| "Purchase did not enroll / email missing?" | `commerce-email.md` |
| "Slow / how does it rank?" | `performance-seo.md` |
| "Which addon should I enable?" | `addons.md` |
| "REST API / why 401 or 403?" | `rest-api.md` |

## Output contract

Report what changed (IDs, option keys, files), why (root cause), how it was verified (SQL, HTTP status, student QA), what is pending, and the rollback. Keep it short enough for the next agent to continue.

## Extension points

Add a reference when a new feature surface appears; extend `scripts/audit-course.php` checks rather than adding one-off SQL to this file.
