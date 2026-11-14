---
name: tutorlms-expert
description: Operate, configure, troubleshoot, and QA Tutor LMS (TutorLMS) on WordPress. Use whenever the user mentions Tutor LMS, courses, lessons, topics, quizzes, assignments, certificates, enrollments, student dashboard, course bundles, prerequisites, content drip, gradebook, or any Tutor LMS problem (lessons blocked by prerequisites, progress not completing, certificate not issued, missing attachments, broken next button, login redirect, false progress). Also use when creating or auditing a course structure, configuring Tutor Pro addons, or simulating a student end-to-end.
compatibility: WordPress with Tutor LMS 3.x/4.x, Tutor Pro, WP-CLI or SSH access, optionally EMCP Tools. Some addons require WooCommerce.
---

# Tutor LMS Expert

Tutor LMS looks simple from the outside and is deceptively stateful underneath. A course is not one record: it is a tree of `topics` → `lesson` / `tutor_quiz` / `tutor_assignments`, plus enrollment records, completion comments, user meta, addon settings, and WooCommerce products. Most "Tutor bugs" are one of those pieces disagreeing with another. This skill gives you the model, the safe operating loop, and the known failure modes so you can fix the cause instead of the symptom.

## The model you must hold in your head

Tutor 4 stores the curriculum as WordPress posts and uses `post_parent` + `menu_order` for nesting:

| Piece | Post type | Parent | Key meta |
|---|---|---|---|
| Course | `courses` | 0 | `tutor_course_price`, `tutor_course_sale_price` (Tutor 4; `_tutor_course_price` is legacy), `_tutor_course_product_id`, `_tutor_is_public_course`, `_course_duration`, `_tutor_course_prerequisites_ids`, `tutor_course_certificate_template` |
| Topic | `topics` | course ID | `menu_order` |
| Lesson | `lesson` | topic ID | `_tutor_course_id_for_lesson` (fallback; Tutor resolves by topic parent), `_video`, `_tutor_attachments`, `_is_preview` |
| Quiz | `tutor_quiz` | topic ID | `tutor_quiz_option`; questions live in `{prefix}tutor_quiz_questions` and answers in `{prefix}tutor_quiz_question_answers` |
| Assignment | `tutor_assignments` | topic ID | `_tutor_course_id_for_assignments`, `assignment_option`, `_tutor_assignment_total_mark`, `_tutor_assignment_pass_mark` |
| Enrollment | `tutor_enrolled` | course ID | `post_author` = student; `_tutor_enrolled_by_order_id`, `_tutor_enrolled_by_product_id` |
| Announcement | `tutor_announcements` | course ID | none needed |
| Bundle | `course-bundle` | 0 | `bundle-course-ids` = `"12,34,56"`; price is derived from its courses (no automatic discount), product via `_tutor_course_product_id` |

Completion is not a post. Tutor records it in `{prefix}comments` with `comment_agent = 'TutorLMSPlugin'`, `comment_type = 'course_completed'`, `comment_post_ID = course_id`, `user_id = student_id`. Lesson completion is user meta `_tutor_completed_lesson_id_<lesson_id>`.

The full inventory (tables, options, addon flags, quiz/assignment schemas) is in `references/data-model.md`. Read it before writing SQL or a one-off script.

## Operating loop

1. **Inspect before touching.** Identify the course ID, its topics/lessons/quizzes/assignments, the students enrolled, the addon flags, and the relevant `tutor_option` keys. Use `scripts/tutor-inspect.sh` or the equivalent WP-CLI commands.
2. **Plan the smallest change that fixes the root cause.** Decide whether the fix belongs to content (posts/meta), configuration (`tutor_option`/addon flags), template (child theme/mu-plugin), or commerce (Woo product/enrollment). Never patch the symptom with CSS or a hardcoded `if`.
3. **Apply idempotently.** Check existence by stable keys (course ID + title, quiz ID + question title, enrollment course + user) before inserting. Re-running the operation must not duplicate data.
4. **Verify with data, not vibes.** Re-read the posts/meta/options and run the relevant SQL. For student-facing changes, complete `references/qa-student.md` as a real student.
5. **Record the change and its rollback.** Note the before-image (post meta, option value, revision ID) so a future agent can undo it.

## Safety rules

- **Back up before bulk or destructive work.** `wp db export` (at least `posts` + `postmeta`) or the change ledger. Never run a destructive SQL write without a backup.
- **Never call `update-page-settings` on the Elementor kit.** It replaces the kit and wipes the design system. Use read-modify-write `wp eval` for kit options.
- **Do not use `run-wp-cli`/SQL to write serialized meta blind.** `maybe_serialize` and `wp_slash` matter. Prefer `update_post_meta()` in a PHP script.
- **Do not block the sale to enforce pedagogy.** Course prerequisites are useful as a recommendation, but a hard block (`tutor_pro_prerequisites_redirect=on`) can make a paid course inaccessible. Default to showing the prerequisite without blocking unless the client explicitly wants a gate.
- **Do not mark progress for content the student could not see.** If a redirect, prerequisite, or permission wall prevents rendering a lesson, completion must not fire.
- **Do not promise a certificate the configuration cannot issue.** `course_completion_process = strict` waits for an instructor to grade assignments; `flexible` lets the student complete the course. Decide this with the client, then verify the certificate page.
- **Do not invent legal or course content.** If a lesson has no source material, say "pendiente de copy" or leave it for the client; never fill it with generic filler that looks authoritative.

## Common jobs

### Create or audit a course structure
Use `scripts/create-course-structure.php` with a JSON map of `{course_id: [{title, lessons: [...]}]}`. It creates topics and lessons, sets `_tutor_course_id_for_lesson`, and is idempotent by title + parent. Then verify counts with `scripts/check-course.php`. Details and the full setup sequence: `references/course-setup.md`.

### Attach media and downloads
Tutor uses `_video` (serialized array: `source`, `source_external_url`/`source_video_id`, `source_html5`, `poster`) and `_tutor_attachments` (array of attachment IDs). Attach ZIP/PDF resources to the lesson that promises them, then verify the lesson renders the "Attachments" tab. Large videos belong on YouTube/Vimeo (unlisted) or a streaming host, not IONOS shared hosting.

### Quizzes
Quizzes are `tutor_quiz` posts with questions in `{prefix}tutor_quiz_questions` and answers in `{prefix}tutor_quiz_question_answers`. `tutor_quiz_option` is a serialized array (`passing_grade`, `attempts_allowed`, `time_limit`, etc.). Remove questions that belong to another course; a quiz for Visa T should not contain asylum questions. Verify the passing grade and that the student can submit before declaring it done.

### Assignments and gradebook
`tutor_assignments` posts need `_tutor_course_id_for_assignments`, `assignment_option` (`time_duration`, `total_mark`, `pass_mark`, `upload_files_limit`, `upload_file_size_limit`) and the total/pass mark metas. The student must be enrolled in the assignment's course to start it; otherwise Tutor returns "No estás autorizado a realizar esta acción". The gradebook scale is global (`gradebook_scale`); Tutor does not provide per-course 40/60 weighting natively.

### Certificates
Assign `tutor_course_certificate_template` on the course. Decide `course_completion_process`: `strict` requires all lessons, quizzes, assignments and instructor grading; `flexible` lets the student complete at 100%. Enable `send_certificate_link_to_course_completion_email` and `show_instructor_name_on_certificate` when the client wants the diploma in the completion email. Verify by completing a real student enrollment and opening `/certificado-de-tutor/?cert_hash=...`.

### Prerequisites and content drip
Course prerequisites use `_tutor_course_prerequisites_ids` and `tutor_pro_show_prerequisites_courses` / `tutor_pro_prerequisites_redirect`. Use them as a recommendation, not a gate, unless the client explicitly wants a sequence. Lesson-level unlocks belong to Content Drip (`enable_content_drip`, `content_drip_type`), not to the course addon.

### Bundles and subscriptions
A bundle is a `course-bundle` post with `bundle-course-ids` and a WooCommerce product. Create the product with `\TUTOR\Course::create_wc_product()` and link it with `\TUTOR\Course::sync_course_with_wc_product()`. Keep it `draft` until pricing and copy are approved. Subscriptions require the `subscription` or `wc-subscriptions` addon and a clear recurring offer; do not enable them speculatively.

### Login, dashboard and redirects
Tutor's student dashboard is a page with `[tutor_dashboard]`; if the configured page is empty, students land on `wp-admin/profile.php` and may see a 500. Add a `login_redirect` filter for non-admins to `/escritorio/` (or the configured dashboard) and keep the dashboard page populated.

### Translations and CSS
Tutor strings live in the `tutor`/`tutor-pro` domains. Use a `gettext` filter in a mu-plugin for small overrides ("Exercise Files" → "Adjuntos"). Put visual fixes in the Customizer or a child theme, not in an inactive snippet plugin. If WPCode is not installed, `wpcode` posts are dead data: move the logic to a mu-plugin and delete the orphans.

## Troubleshooting matrix

| Symptom | Likely cause | Fix |
|---|---|---|
| Lesson/quiz/assignment 302 → `#tutor_prereq` | `_tutor_course_prerequisites_ids` requires a course the student does not have | Remove the prerequisite or enroll the student; do not leave a silent redirect |
| Progress rises without showing the lesson | Auto-complete hook fires before the prerequisite redirect | Gate completion on real lesson access (enrolled + not redirected) |
| "No estás autorizado a realizar esta acción" on an assignment | Student is not enrolled in the assignment's course | Enroll the student; verify `_tutor_course_id_for_assignments` |
| "Nonce no coincidente" on an AJAX action | Stale nonce/session, not a permission issue | Reload the page; regenerate the nonce for the same user session |
| Certificate missing at 100% | `course_completion_process = strict` and an assignment is `Pending` | Grade the assignment or switch to `flexible`; verify the certificate page |
| "Archivos adjuntos" tab empty | `_tutor_attachments` missing on the lesson | Attach the attachment ID array to the lesson |
| Next button does nothing | `next_id = 0` or `next_is_locked` (course not public / not enrolled) | Check `get_course_prev_next_contents_by_id()`, make the course public, or fix the curriculum order |
| Buttons stretch to 100% or 500px | A global CSS rule targeting `button` or `.tutor-btn-icon` | Remove the global rule; scope fixes to the component |
| Login ends in `wp-admin/profile.php` 500 | No student dashboard redirect | `login_redirect` filter to `/escritorio/`; populate the dashboard page |
| Dates/times off by hours | `timezone_string` empty and `gmt_offset = 0` | Set `America/New_York` (or the client's zone) |

More detail and exact SQL in `references/troubleshooting.md`.

## Scripts

- `scripts/tutor-inspect.sh` — inventory courses, curriculum counts, addons, options and enrollments (read-only).
- `scripts/create-course-structure.php` — idempotent topic/lesson importer from JSON.
- `scripts/enroll-student.php` — create a student and enroll them in a course (idempotent).
- `scripts/check-course.php` — verify counts, attachments, videos, prerequisites and completion settings.
- `scripts/audit-course.php` — deeper audit of one course: missing course links, empty quizzes, assignments without a course, attachments, certificate, product, price, visibility, duplicates, demo orphans (read-only).

All scripts accept `--path=<wordpress-root>` through WP-CLI where relevant, and print a report suitable for pasting into an agent context.

## References

- `references/data-model.md` — tables, post types, meta keys, quiz/assignment schemas, completion records.
- `references/addons.md` — Tutor Pro addons, what each does, how to check and enable them.
- `references/course-setup.md` — step-by-step course setup from zero to sellable.
- `references/troubleshooting.md` — symptom → root cause → fix, with SQL/WP-CLI.
- `references/qa-student.md` — end-to-end student QA checklist and how to simulate it.
- `references/audit-and-migration.md` — course audit checklist, duplicate/demo detection, and migration from LearnDash/LifterLMS/LearnPress.
- `references/accessibility-compliance.md` — WCAG 2.2 AA for lessons/quizzes, captions/audio description, UPL/EOIR limits, privacy (CCPA/CPRA/GDPR), claims and certificate disclaimers.
- `references/commerce-email.md` — WooCommerce product/enrollment states, bundles, coupons, subscriptions, FluentSMTP, DMARC, transactional email sequences.
- `references/performance-seo.md` — Tutor asset loading, object cache, slow queries, video hosting, course SEO/schema, GA4/Meta events.

## Which reference to read

| Question | Reference |
|---|---|
| "What post/meta/table is this?" | `data-model.md` |
| "How do I create a course from zero?" | `course-setup.md` |
| "Why is this lesson blocked / progress wrong / certificate missing?" | `troubleshooting.md` |
| "Can a student complete the flow?" | `qa-student.md` |
| "Is this course ready to sell? Can I migrate it?" | `audit-and-migration.md` |
| "Is it accessible/legal to sell this?" | `accessibility-compliance.md` |
| "Why did the purchase not enroll / the email not arrive?" | `commerce-email.md` |
| "Why is it slow / how does it rank?" | `performance-seo.md` |
| "Which addon should I turn on?" | `addons.md` |

## Output contract

When you finish a Tutor task, report:

1. **What changed** — course/lesson/quiz IDs, option keys, files.
2. **Why** — the root cause you fixed, not just the symptom.
3. **How it was verified** — SQL counts, HTTP status, student QA result.
4. **What is still pending** — media, copy, client decisions, or unverified paths.
5. **Rollback** — the before-image or the exact reverse operation.

Keep the report short. The next agent should be able to continue from it without re-reading the whole site.
