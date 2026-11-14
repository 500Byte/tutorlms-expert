# Tutor LMS data model

> Read this before writing SQL or a one-off script. Tutor 4 is post-based; most "missing" data is a post with the wrong `post_parent` or a meta key with the wrong type.

## Table prefix

Use `$wpdb->prefix` inside PHP. On some hosts the prefix has no underscore (e.g. `dtds`), so `$wpdb->prefix . 'tutor_quiz_questions'` is correct while a hardcoded `wp_` is not. In SQL, confirm with `SHOW TABLES LIKE '%tutor%'`.

## Post types

| Post type | Purpose | Parent |
|---|---|---|
| `courses` | Course | 0 |
| `topics` | Curriculum section | course |
| `lesson` | Lesson | topic |
| `tutor_quiz` | Quiz | topic |
| `tutor_assignments` | Assignment | topic |
| `tutor_enrolled` | Enrollment record | course |
| `tutor_announcements` | Course announcement | course |
| `course-bundle` | Bundle of courses | 0 |
| `tutor_certificate` | Certificate template | 0 |
| `tutor_quiz_attempts` (table) | Attempts | — |

Ordering inside a topic is `menu_order`; topics are ordered by `menu_order` on the course. If two lessons share the same `menu_order`, the next/previous calculation becomes unpredictable.

## Course meta

| Meta key | Type | Notes |
|---|---|---|
| `tutor_course_price` | number | Regular price (Tutor 4 native). Some installs still carry legacy `_tutor_course_price`; verify both. |
| `tutor_course_sale_price` | number | 0 or empty for no sale |
| `_tutor_course_price_type` | string | `paid` / `free` |
| `_tutor_course_product_id` | int | WooCommerce product ID |
| `_tutor_is_public_course` | `yes`/`no` | Public courses can be viewed without enrollment |
| `_course_duration` | array | `['hours' => 2, 'minutes' => 2]` |
| `_tutor_course_prerequisites_ids` | array | Course IDs that must be completed (recommendation or gate) |
| `tutor_course_certificate_template` | string | e.g. `tutor_cb_1717` |
| `_tutor_course_benefits` | string | Newline-separated |
| `_tutor_course_requirements` | string | Newline-separated |
| `_tutor_course_target_audience` | string | Newline-separated |
| `_tutor_course_level` | string | `beginner` / `intermediate` / `advanced` |
| `_tutor_enable_qa` | `yes`/`no` | Per-course Q&A |
| `_tutor_course_settings` | array | Max students, drip, enrollment period |

## Lesson meta

| Meta key | Type | Notes |
|---|---|---|
| `_tutor_course_id_for_lesson` | int | Parent course fallback; Tutor resolves the course from the topic parent, but the builder writes this meta. Missing it is a risk for progress and certificates. |
| `_video` | array | `['source' => 'youtube', 'source_video_id' => '...', 'source_external_url' => '...', 'source_html5' => '', 'poster' => '']` |
| `_tutor_attachments` | array | Attachment IDs, e.g. `['2111']` |
| `_is_preview` | `yes`/`no` | Lesson visible without enrollment |
| `_thumbnail_id` | int | Featured image |

A lesson without `_tutor_course_id_for_lesson` can still resolve through its topic, but the builder writes the meta and some progress/certificate paths rely on it. Audit it; do not assume the topic parent is enough on every version.

> Tutor 4's native price meta is `tutor_course_price` (no leading underscore). The builder and the bundle addon read that key. `_tutor_course_price` is legacy; if both exist, keep them in sync or migrate to the native key.

## Quiz schema

Quiz post: `post_type = tutor_quiz`, `post_parent = topic_id`, `tutor_quiz_option` serialized array.

Questions: `{prefix}tutor_quiz_questions`

| Column | Notes |
|---|---|
| `question_id` | PK |
| `quiz_id` | Quiz post ID |
| `question_title` | Text |
| `question_type` | `multiple_choice`, `true_false`, `short_answer`, etc. |
| `question_mark` | Decimal |
| `question_settings` | Serialized array (`answer_required`, `question_mark`, `question_type`, `randomize_question`, `show_question_mark`, `has_multiple_correct_answer`) |
| `question_order` | Sort order |

Answers: `{prefix}tutor_quiz_question_answers`

| Column | Notes |
|---|---|
| `answer_id` | PK |
| `belongs_question_id` | Question ID |
| `belongs_question_type` | Usually `multiple_choice` |
| `answer_title` | Text |
| `is_correct` | 1/0 |
| `answer_view_format` | `text` |
| `answer_order` | Sort order |

`tutor_quiz_option` keys that matter: `passing_grade` (70 = 70%), `attempts_allowed`, `limit_attempts_allowed`, `time_limit` (`['time_type' => 'minutes', 'time_value' => 0]`), `question_layout_view`, `enable_pagination`, `questions_order`.

## Assignment schema

Post: `tutor_assignments`, parent topic.

| Meta key | Type | Notes |
|---|---|---|
| `_tutor_course_id_for_assignments` | int | Course ID (required for permission checks) |
| `assignment_option` | array | `['time_duration' => ['value' => 7, 'time' => 'days'], 'total_mark' => 10, 'pass_mark' => 6, 'upload_files_limit' => 1, 'upload_file_size_limit' => 2]` |
| `_tutor_assignment_total_mark` | int | Usually same as `total_mark` |
| `_tutor_assignment_pass_mark` | int | Usually same as `pass_mark` |

Starting an assignment without enrollment returns `tutor_utils()->error_message()` → "No estás autorizado a realizar esta acción". The nonce error is different ("Nonce no coincidente").

Submissions are comments: `comment_type = tutor_assignment`, `comment_post_ID = assignment_id`, `comment_approved = submitting|submitted`, with `uploaded_attachments` in comment meta.

## Enrollment

`tutor_enrolled` post: `post_parent = course_id`, `post_author = student_id`, `post_status = completed|enrolled|cancelled`.

| Meta key | Notes |
|---|---|
| `_tutor_enrolled_by_order_id` | WooCommerce order ID or 0 |
| `_tutor_enrolled_by_product_id` | WooCommerce product ID |

`tutor_utils()->is_enrolled($course_id, $user_id)` reads this record. For QA, create the post directly if `do_enroll()` is not available.

## Completion

Course completion is a comment:

```sql
SELECT * FROM {prefix}comments
WHERE comment_agent = 'TutorLMSPlugin'
  AND comment_type = 'course_completed'
  AND comment_post_ID = <course_id>
  AND user_id = <student_id>;
```

`CourseModel::mark_course_as_completed($course_id, $user_id)` inserts it. `tutor_utils()->is_completed_course($course_id, $user_id)` checks it. `get_course_completed_percent()` counts lessons, quizzes and assignments, so "9 de 9 lecciones" can include non-lesson items.

Lesson completion is user meta: `_tutor_completed_lesson_id_<lesson_id>` with a timestamp.

## Key options (`tutor_option`, serialized array)

| Key | Values | Effect |
|---|---|---|
| `course_completion_process` | `strict` / `flexible` | Strict waits for instructor grading; flexible allows completion at 100% |
| `auto_course_complete_on_all_lesson_completion` | `on`/`off` | Complete the course when all content is done |
| `control_video_lesson_completion` | `on`/`off` | Require a video percentage to complete |
| `required_percentage_to_complete_video_lesson` | int | Default 80 |
| `enable_course_review` | `on`/`off` | Reviews |
| `enable_course_announcements` | `on`/`off` | Announcements |
| `enable_lesson_notes` | `on`/`off` | Student notes |
| `send_certificate_link_to_course_completion_email` | `on`/`off` | Certificate link in email |
| `show_instructor_name_on_certificate` | `on`/`off` | Instructor name on diploma |
| `tutor_pro_show_prerequisites_courses` | `on`/`off` | Show prerequisites on the course page |
| `tutor_pro_prerequisites_redirect` | `on`/`off` | Hard-block access when prerequisites are unmet |
| `gradebook_scale` | number | e.g. 4.0 |
| `email_to_students`, `email_to_teachers`, `email_to_admin` | arrays | Notification toggles |

## Useful checks

```sql
-- Counts per course
SELECT c.ID AS course_id, COUNT(DISTINCT t.ID) AS topics, COUNT(l.ID) AS lessons
FROM {prefix}posts c
LEFT JOIN {prefix}posts t ON t.post_parent = c.ID AND t.post_type = 'topics'
LEFT JOIN {prefix}posts l ON l.post_parent = t.ID AND l.post_type = 'lesson'
WHERE c.post_type = 'courses'
GROUP BY c.ID;

-- Lessons missing the course link
SELECT l.ID, l.post_title
FROM {prefix}posts l
LEFT JOIN {prefix}postmeta pm ON pm.post_id = l.ID AND pm.meta_key = '_tutor_course_id_for_lesson'
WHERE l.post_type = 'lesson' AND pm.meta_value IS NULL;

-- Attachments on lessons
SELECT post_id, meta_value FROM {prefix}postmeta WHERE meta_key = '_tutor_attachments';
```

```bash
wp eval 'print_r(tutor_utils()->get_course_prev_next_contents_by_id(<lesson_id>));'
wp eval 'echo tutor_utils()->get_course_completed_percent(<course_id>, <user_id>);'
wp eval 'echo tutor_utils()->is_completed_course(<course_id>, <user_id>) ? "yes" : "no";'
```
