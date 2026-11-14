# Tutor Pro addons

> **Read when:** you need to inspect, enable or debug a Tutor Pro addon such as content bank or subscriptions.

Tutor Pro ships addons as directories under `wp-content/plugins/tutor-pro/addons/`. The enabled state lives in the `tutor_addons_config` option, keyed by the addon bootstrap file, with `is_enable` = 1/0.

## Inspect

```bash
# Enabled / disabled addons
wp eval '$c=get_option("tutor_addons_config"); foreach($c as $k=>$v){ echo basename(dirname($k))." | ".($v["is_enable"]??"?")."\n"; }' | sort

# Addon directories available in this install
ls wp-content/plugins/tutor-pro/addons
```

## Addon map

| Addon | What it adds | Notes |
|---|---|---|
| `tutor-assignments` | Practical tasks with file upload, deadline, marks, grading | The strongest differentiator for "learn by doing" courses. Needs `_tutor_course_id_for_assignments` and enrollment. |
| `gradebook` | Gradebook and scale | Global scale (`gradebook_scale`); no native 40/60 weighting per course. |
| `tutor-certificate` | Certificate issuance and legacy template builder | Assign `tutor_course_certificate_template` per course. The newer **Tutor LMS Certificate Builder** is a separate plugin; verify which builder the install uses. Verify with a real completed student. |
| `tutor-course-attachments` | Per-lesson attachments tab | Requires `_tutor_attachments` on the lesson. |
| `tutor-course-preview` | Free preview lessons | Mark a lesson `_is_preview = yes`; ideal for lead capture. |
| `tutor-prerequisites` | Course prerequisites | `_tutor_course_prerequisites_ids`; `tutor_pro_show_prerequisites_courses` shows them; `tutor_pro_prerequisites_redirect` hard-blocks. Prefer recommendation over gate. |
| `content-drip` | Release content by days/date/prerequisite | Per-course `enable_content_drip` and `content_drip_type`. Best for cohorts, not self-paced. |
| `course-bundle` | Sell several courses together | `course-bundle` post + `bundle-course-ids` + Woo product. Keep draft until pricing is approved. |
| `subscription` / `wc-subscriptions` | Recurring access | Only with a defined recurring offer. |
| `tutor-email` | Transactional emails and templates | Configure an SMTP plugin (e.g. FluentSMTP, WP Mail SMTP) first; `email_from_address` must be a real mailbox. |
| `tutor-notifications` | In-app notification bell | Optional; needs the addon enabled. |
| `tutor-report` | Reports for revenue, courses, students | Useful after the first cohort. |
| `calendar` | Course calendar | Pairs with live classes and assignment deadlines. |
| `tutor-zoom` / `google-meet` | Live class integrations | Require the external account/API credentials. |
| `tutor-multi-instructors` | Multiple instructors per course | Only if the client has more than one teacher. |
| `social-login` | Google/Facebook login | Reduces signup friction; configure the OAuth apps. |
| `quiz-import-export` | Import/export quiz questions | Useful when migrating question banks. |
| `content-bank` | Reusable content library | Useful for shared lessons across courses. |
| `enrollments` | Manual enrollment management | Useful for cohorts and comped access. |
| `h5p` | Interactive H5P content | Only if the team will produce interactive content. |
| `buddypress` | Community profiles/activity | Heavy; usually not needed for a course business. |
| `pmpro` / `restrict-content-pro` | Membership integrations | Only if the site already uses those plugins. |
| `tutor-wpml` / `tutor-weglot` | Multilingual course content | GTranslate is a different layer; do not enable both blindly. |

## Recommended order for a course business

1. **Assignments + gradebook** — turn passive video into practice.
2. **Certificate** — complete the promise of the course.
3. **Course preview** — one free lesson per course for ads/lead capture.
4. **Reviews + announcements** — social proof and updates.
5. **Course bundle** — raise average order value.
6. **Email** — welcome, reminders, completion, certificate.
7. **Prerequisites / content drip** — only when the pedagogy or cohort model requires it.
8. **Zoom/calendar** — live case clinics.
9. **Subscriptions** — only with a recurring offer.

## Enabling an addon

The safe way is the Tutor Pro addons screen. If you must do it programmatically, read `tutor_addons_config`, set `is_enable` for the addon key, and write it back with `update_option`:

```php
$config = get_option('tutor_addons_config');
$key = 'tutor-pro/addons/tutor-assignments/tutor-assignments.php';
$config[$key]['is_enable'] = 1;
update_option('tutor_addons_config', $config);
```

Verify the addon actually loads and its settings appear. Some addons need a page, a product, or an external credential before they do anything useful.
