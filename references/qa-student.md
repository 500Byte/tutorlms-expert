# Student QA

> **Read when:** you must verify a course end to end as a real student before launching or handing it over.

A course is not done when an admin can see it. It is done when a real student can buy or access it, consume the content, complete it and receive the certificate. This checklist simulates that path.

## 1. Create the QA student

Use `scripts/enroll-student.php` or do it manually:

```php
$user_id = wp_create_user('qa.student', 'change-me-strong-password', 'qa.student@example.com');
$u = new WP_User($user_id);
$u->set_role('subscriber');

$enrollment_id = wp_insert_post([
    'post_type' => 'tutor_enrolled',
    'post_title' => 'QA Student',
    'post_status' => 'completed',
    'post_parent' => $course_id,
    'post_author' => $user_id,
]);
update_post_meta($enrollment_id, '_tutor_enrolled_by_order_id', 0);
update_post_meta($enrollment_id, '_tutor_enrolled_by_product_id', $product_id);
```

Verify `tutor_utils()->is_enrolled($course_id, $user_id)` is true.

## 2. Login

- Go to `/wp-login.php`.
- Submit the form.
- **Pass:** 302 to `/dashboard/` and 200 on the dashboard.
- **Fail:** `wp-admin/profile.php` 500, loop, or maintenance page.

If the site is in Elementor maintenance mode, logged-in users should still see the front (`exclude_mode = logged_in`). Anonymous users will see 503; that is expected and should be called out.

## 3. Dashboard

- "Enrolled courses" shows the course.
- Progress starts at 0 or the current state.
- "Continue learning" points to a real lesson, not an empty hash or the assignment only.

## 4. Course page

- Price, duration, certificate and requirements render.
- A logged-in enrolled student does not see a signup CTA.
- "Start learning" / "Continue" opens a lesson.
- If the curriculum is intentionally hidden, there is still a visible entry point to the content.

## 5. Lesson

- Opens with HTTP 200 (no `#tutor_prereq`, no login redirect).
- Content is readable: paragraphs, headings, lists; no wall of text.
- Sidebar lists topics and lessons and navigates.
- "Next" advances to the next item; on the last item it is hidden or goes to the quiz/assignment.
- "Previous" does not jump to an unrelated item.

## 6. Completion

Open each lesson and confirm:
- The lesson is marked complete (if the design is "complete on open").
- The progress percentage increases coherently.
- No lesson is marked complete when the content did not render.

Check the data:

```php
tutor_utils()->get_course_completed_percent($course_id, $user_id);
tutor_utils()->is_completed_course($course_id, $user_id);
```

## 7. Attachments

On the lesson that promises a download:
- The "Attachments" tab/button is visible.
- The file downloads with HTTP 200 and the expected size.
- ZIP: `unzip -t` reports no errors.

## 8. Quiz

- Opens with the expected number of questions.
- Radio buttons work; submit works.
- Result page shows score, pass/fail and review.
- The passing grade matches the configuration (e.g. 70%).
- Questions belong to this course and have no typos.

## 9. Assignment

- "Start assignment" opens the submission form.
- A PDF can be attached; the confirmation shows the file count.
- The submission appears as `Pending` in the student view.
- If the certificate depends on grading, say so explicitly; the student cannot complete until the instructor grades.

## 10. Certificate

- At 100% and with the completion mode satisfied, the certificate is listed in `/dashboard/account/certificates/`.
- Opening `/certificado-de-tutor/?cert_hash=...` returns 200.
- The certificate shows the student name and the course title.
- The completion email contains the certificate link if `send_certificate_link_to_course_completion_email` is on.

## 11. Mobile (390×844)

- Course and lesson have no horizontal page scroll (`scrollWidth == clientWidth`).
- Tap targets are at least 44×44 px where possible (share, tabs, next/previous, buttons).
- Lesson tabs do not hide a tab without a visible scroll cue.

## 12. Report

Use this structure:

```markdown
# QA — <course> — <date>

**State:** APTO / APTO con salvedad / NO APTO
**Blockers:** n
**Important:** n
**Minor:** n

## Steps
| Step | Expected | Actual | Severity | Evidence |
|---|---|---|---|---|
| Login | ... | ... | ... | ... |

## Bugs
### Blocking
1. ...

### Important
1. ...

### Minor
1. ...

## Not verifiable
- ...

## Recommendation
1. ...
```

Do not invent results. If a video is not uploaded, say "not verifiable". If a certificate requires grading, say "pending instructor action". The client will make better decisions with an honest "not verified" than with a green check that was never tested.

## Cleanup

The QA user and enrollment can stay for future regressions or be removed:

```php
wp_delete_user($user_id, 2); // reassign content to admin
// or delete the tutor_enrolled post and the user
```

If you keep it, record the credentials and the course in the project notes. Never leave a QA admin account without a documented purpose.
