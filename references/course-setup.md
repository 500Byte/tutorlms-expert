# Course setup: from zero to sellable

> **Read when:** you are taking a new Tutor course from empty install to a sellable, certifiable product.

This is the sequence that produces a course a real student can buy, consume, complete and certify. Adapt the copy and the price to the client; never invent the legal content.

## 0. Decide before building

- **Price and product.** Tutor creates a WooCommerce product when the course has a price. Decide currency and tax with the client.
- **Completion mode.** `strict` requires instructor grading of assignments; `flexible` lets the student complete at 100%. Default to `flexible` unless feedback is part of the paid promise.
- **Certificate.** Confirm the certificate template, the name that appears, and whether the completion email includes the link.
- **Audience and language.** One course = one clear outcome. Do not mix two audiences in one curriculum.
- **Public or private.** `_tutor_is_public_course = yes` lets visitors see the course and preview lessons. Private courses are for cohorts or internal training.

## 1. Create the course post

Required meta:

```php
$course_id = wp_insert_post([
    'post_type' => 'courses',
    'post_title' => 'Course title',
    'post_content' => '<p>Short description...</p>',
    'post_status' => 'publish',
]);
update_post_meta($course_id, 'tutor_course_price', 99);
update_post_meta($course_id, 'tutor_course_sale_price', 0);
update_post_meta($course_id, '_tutor_course_price_type', 'paid');
update_post_meta($course_id, '_tutor_is_public_course', 'yes');
update_post_meta($course_id, '_course_duration', ['hours' => 2, 'minutes' => 0]);
update_post_meta($course_id, '_tutor_course_level', 'intermediate');
update_post_meta($course_id, '_tutor_course_benefits', "Outcome 1\nOutcome 2");
update_post_meta($course_id, '_tutor_course_requirements', "Requirement 1");
update_post_meta($course_id, '_tutor_course_target_audience', "Audience 1\nAudience 2");
update_post_meta($course_id, '_tutor_enable_qa', 'yes');
```

Set a featured image. If the site monetizes with WooCommerce (`monetize_by = wc`), link the product with `_tutor_course_product_id`; Tutor can create it when saving from the builder, or use `\TUTOR\Course::create_wc_product()` / `sync_course_with_wc_product()`.

## 2. Build the curriculum

Use `scripts/create-course-structure.php` with a JSON map:

```json
{
  "123": [
    {"title": "Tema 1: Fundamentos", "lessons": ["1.1 ¿Qué es...?", "1.2 Requisitos..."]},
    {"title": "Tema 2: Estrategia", "lessons": ["2.1 Entrevista..."]}
  ]
}
```

The script creates `topics` and `lesson` posts, sets `_tutor_course_id_for_lesson`, and is idempotent by title + parent. Verify with `scripts/check-course.php`.

Rules:
- Every lesson belongs to a topic (`post_parent` = topic ID).
- `menu_order` is sequential inside the topic.
- The last item of a course is usually a quiz or an assignment; the next/previous calculation includes it.
- Do not leave lessons with `post_parent = 0`.

## 3. Write the content

Each lesson needs a real body, not a placeholder. Prefer:
1. A one-paragraph objective.
2. The material, with headings and lists. Short paragraphs beat walls of text.
3. A resource box (video, PDF, template, external link).
4. A "what you should be able to do" close.

If a lesson has no source, say `pendiente de copy` in the plan. Do not fill legal content with generic filler.

## 4. Add media

- **Video:** `_video` on the lesson. For large files use YouTube/Vimeo unlisted and `source = external_url` or `youtube`; do not host multi-hundred-MB files on shared hosting.
- **Attachments:** `_tutor_attachments` = array of attachment IDs. Attach the ZIP/PDF to the lesson that promises it.
- **Featured image:** `_thumbnail_id` on the course and, optionally, the lesson.
- **Preview:** `_is_preview = yes` on one strong lesson per course.

## 5. Quiz

Create a `tutor_quiz` under the final topic. Add questions to `{prefix}tutor_quiz_questions` and answers to `{prefix}tutor_quiz_question_answers`. Set `tutor_quiz_option` with `passing_grade` (70), `attempts_allowed` (3), and no timer unless the client wants one.

Keep the quiz scoped to the course. A quiz for one course should not contain questions from another course. Remove typos and ambiguous options.

## 6. Assignment

Create a `tutor_assignments` post under the relevant topic. Set:

```php
update_post_meta($assignment_id, '_tutor_course_id_for_assignments', $course_id);
update_post_meta($assignment_id, 'assignment_option', [
    'time_duration' => ['value' => 7, 'time' => 'days'],
    'total_mark' => 10,
    'pass_mark' => 6,
    'upload_files_limit' => 1,
    'upload_file_size_limit' => 2,
]);
update_post_meta($assignment_id, '_tutor_assignment_total_mark', 10);
update_post_meta($assignment_id, '_tutor_assignment_pass_mark', 6);
```

The student must be enrolled in the course to start it. The deliverable should be concrete: a PDF, a filled form, a case file. Grade it from the assignment screen.

## 7. Certificate

Assign `tutor_course_certificate_template` to the course. Decide:
- `course_completion_process`: `strict` or `flexible`.
- `send_certificate_link_to_course_completion_email`: `on` if the student should receive the link.
- `show_instructor_name_on_certificate`: `on` if the instructor signs.

Complete a real enrollment and verify `/certificado-de-tutor/?cert_hash=...` renders the student name and course title.

## 8. Preview and catalog

- Mark one lesson as preview.
- Put the course in the right `course-category` and `course-tag`.
- If the site uses a course list shortcode (`[tutor_course]`), confirm the new course appears.
- If a course bundle exists, add the course to `bundle-course-ids` and re-check the bundle price.

## 9. Publish and verify

Checklist:

- [ ] Course is `publish` and `_tutor_is_public_course = yes` (or intentionally private).
- [ ] Product exists, has the right price, and is linked.
- [ ] Every lesson has `_tutor_course_id_for_lesson`.
- [ ] Every promised attachment exists in `_tutor_attachments`.
- [ ] The quiz has the intended questions and passing grade.
- [ ] The assignment has a course ID and an enrollment path.
- [ ] The certificate template is assigned and the completion mode is deliberate.
- [ ] One preview lesson is visible without enrollment.
- [ ] A QA student can complete the flow end-to-end (`references/qa-student.md`).

## 10. After launch

- Send the first announcement with the course updates and the live class date.
- Ask for a review after completion.
- Add the course to the bundle if it belongs there.
- Keep the duration and the "last updated" date current.
