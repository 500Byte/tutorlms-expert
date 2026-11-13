# Troubleshooting Tutor LMS

Each entry: symptom → root cause → fix → verification. Prefer the cause, not the symptom.

## Lessons, quizzes or assignments redirect to `#tutor_prereq`

**Symptom:** any content URL returns 302 to the course page with `#tutor_prereq`.

**Cause:** `_tutor_course_prerequisites_ids` on the course requires a course the student does not have. `tutor_pro_prerequisites_redirect` (or the addon behavior) blocks access, sometimes without a clear message.

**Fix:** decide the policy.

```php
// Recommendation only, no gate
update_post_meta($course_id, '_tutor_course_prerequisites_ids', []);
update_option('tutor_pro_show_prerequisites_courses', 'off');
update_option('tutor_pro_prerequisites_redirect', 'off');
```

If the client wants a real sequence, keep the prerequisite but enroll the student in the prerequisite course or show an explicit message and a link. Never leave a silent redirect.

**Verify:** the content URL returns 200 and contains lesson text; no `#tutor_prereq`.

## Progress increases without rendering the lesson

**Symptom:** visiting blocked lesson URLs raises the progress bar (e.g. 0 → 78%).

**Cause:** an auto-complete hook (`template_redirect` or similar) marks the lesson before the prerequisite/redirect logic runs.

**Fix:** remove the block, or gate completion on real access:

```php
if (!is_singular('lesson') || !is_user_logged_in()) return;
$lesson_id = get_queried_object_id();
$course_id = tutor_utils()->get_course_id_by_subcontent($lesson_id);
if (!$course_id || !tutor_utils()->is_enrolled($course_id)) return;
// Do not complete if a prerequisite redirect will fire.
```

If the client's policy is "complete on open", that is fine — but only when the content actually renders. Clean false progress:

```php
foreach (get_user_meta($user_id) as $key => $value) {
    if (strpos($key, '_tutor_completed_lesson_id_') === 0) {
        delete_user_meta($user_id, $key);
    }
}
```

## "No estás autorizado a realizar esta acción" on an assignment

**Cause:** the student is not enrolled in the assignment's course. The nonce error is a different message ("Nonce no coincidente").

**Fix:** verify `_tutor_course_id_for_assignments` and `is_enrolled`:

```php
echo tutor_utils()->get_course_id_by('assignment', $assignment_id);
echo tutor_utils()->is_enrolled($course_id, $user_id) ? 'yes' : 'no';
```

Create the enrollment if needed (`scripts/enroll-student.php`). Do not debug the button before checking the enrollment.

## "Nonce no coincidente" on an AJAX action

**Cause:** stale nonce or a nonce generated for a different session/user. Not a permission problem.

**Fix:** reload the page so the form gets a fresh nonce. If you are testing with `curl`, generate the nonce for the same user session that owns the cookie; a nonce generated in WP-CLI as user 0 will not match a logged-in cookie.

## Certificate not issued at 100%

**Cause:** `course_completion_process = strict` and an assignment is `Pending` (not graded). In strict mode `is_completed_course()` stays false until the instructor grades.

**Fix:** decide the product policy.

```php
$opt = get_option('tutor_option');
$opt['course_completion_process'] = 'flexible'; // or keep strict and grade
update_option('tutor_option', $opt);
```

For a one-off test, grade the submission or mark the course complete:

```php
$can = \Tutor\Models\CourseModel::can_complete_course($course_id, $user_id);
if (true === $can) {
    \Tutor\Models\CourseModel::mark_course_as_completed($course_id, $user_id);
}
```

**Verify:** `tutor_utils()->is_completed_course()` is true and `/escritorio/account/certificates/` lists the course. Open the certificate URL and confirm the student name and course title.

## "Archivos adjuntos" tab empty

**Cause:** `_tutor_attachments` is missing on the lesson, even if the ZIP exists in the Media Library.

**Fix:**

```php
update_post_meta($lesson_id, '_tutor_attachments', [(string) $attachment_id]);
```

**Verify:** `wp post meta get <lesson_id> _tutor_attachments --format=json` returns the ID array and the lesson renders the attachments tab.

## Next button does nothing

**Cause:** `next_id = 0` (last item or broken curriculum order) or `next_is_locked` (course not public and student not enrolled).

**Fix:** inspect:

```php
print_r(tutor_utils()->get_course_prev_next_contents_by_id($lesson_id));
```

If `next_id` is 0 in the middle of the course, fix `post_parent`/`menu_order`. If the course is private, make it public or enroll the student.

## Buttons stretch to 100% or 500px

**Cause:** a global CSS rule such as `button { width: 100% }` or `.tutor-btn-icon { width: 500px }` applied to every button.

**Fix:** remove the global rule and scope the fix to the component. This usually comes from a snippet plugin or a legacy stylesheet, not from Tutor itself.

## Login ends in `wp-admin/profile.php` with HTTP 500

**Cause:** no student dashboard redirect; the profile page is blocked by a security plugin.

**Fix:** add a `login_redirect` filter for non-admins:

```php
add_filter('login_redirect', function ($redirect_to, $requested, $user) {
    if ($user instanceof WP_User && !user_can($user, 'manage_options')) {
        return home_url('/escritorio/');
    }
    return $redirect_to;
}, 10, 3);
```

Also make sure the page configured in `tutor_dashboard_page_id` contains `[tutor_dashboard]`. An empty page is a dashboard that goes nowhere.

## Dates/times off by hours

**Cause:** `timezone_string` is empty and `gmt_offset = 0`; Tutor shows UTC.

**Fix:**

```php
update_option('timezone_string', 'America/New_York');
update_option('gmt_offset', '');
```

## WhatsApp or chat widget covers the course UI

**Fix (Joinchat example):**

```css
body.single-courses .joinchat,
body.single-lesson .joinchat { display: none !important; }
```

Scope to the post types, not the whole site, unless the client wants it gone everywhere.

## WPCode snippets exist but do nothing

**Cause:** the WPCode plugin is not installed; `wpcode` posts are orphan data.

**Fix:** move the logic to a mu-plugin (`wp-content/mu-plugins/`) or a child theme, verify it loads, and delete the orphan posts after a backup. A `gettext` filter and a small `template_redirect` hook are typical.

## Dashboard says "100%" but "Completado 0"

**Cause:** the progress percentage counts all content items (lessons, quiz, assignment), while the "completed" tile checks `is_completed_course()`. In strict mode, the course is not completed until grading; the labels are also generic.

**Fix:** align the completion mode with the promise, then re-check `is_completed_course()`. This is often a copy/UX issue, not a data bug.

## Hidden curriculum leaves no navigation

**Symptom:** the course page hides "Course Content" (intentional design) and the student has no map of the course.

**Cause:** hiding `.tutor-course-content-title` and `.tutor-accordion` without an alternative entry point.

**Fix:** keep at least one visible CTA to a lesson ("Start learning" / "Continue") for enrolled students, and rely on the lesson sidebar for navigation. Hiding the marketing curriculum is fine; hiding all navigation is not.

## Lesson body looks like a wall of text

**Cause:** content generated as one `<p>` with `<br>` instead of paragraphs, headings and lists.

**Fix:** regenerate the body with real `<p>`, `<h2>`, `<h3>`, `<ul>`/`<li>`. Remove empty `<li>` items. Keep the reading width around 70–80 characters.

## Lesson is a stub with no video

**Cause:** the lesson promises a video in the title but `_video` is empty.

**Fix:** upload the video (YouTube/Vimeo unlisted for large files) and set `_video`; or remove the "(video)" promise from the title until the media exists. Do not leave a broken player or a "pending link" paragraph.
