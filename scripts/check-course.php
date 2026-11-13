<?php
/**
 * Verify a Tutor LMS course before publishing.
 *
 * Usage:
 *   wp eval-file check-course.php <course_id>
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$course_id = isset($args[0]) ? (int) $args[0] : 0;
if (!$course_id) {
    WP_CLI::error('Usage: wp eval-file check-course.php <course_id>');
}

$course = get_post($course_id);
if (!$course || 'courses' !== $course->post_type) {
    WP_CLI::error("Course {$course_id} not found.");
}

$topics = get_posts([
    'post_type'   => 'topics',
    'post_parent' => $course_id,
    'post_status' => 'any',
    'numberposts' => -1,
    'orderby'     => 'menu_order',
    'order'       => 'ASC',
]);

$lessons = [];
$quizzes = [];
$assignments = [];
foreach ($topics as $topic) {
    $lessons = array_merge($lessons, get_posts([
        'post_type'   => 'lesson',
        'post_parent' => $topic->ID,
        'post_status' => 'any',
        'numberposts' => -1,
    ]));
    $quizzes = array_merge($quizzes, get_posts([
        'post_type'   => 'tutor_quiz',
        'post_parent' => $topic->ID,
        'post_status' => 'any',
        'numberposts' => -1,
    ]));
    $assignments = array_merge($assignments, get_posts([
        'post_type'   => 'tutor_assignments',
        'post_parent' => $topic->ID,
        'post_status' => 'any',
        'numberposts' => -1,
    ]));
}

$missing_course_link = [];
$with_attachments = 0;
$with_video = 0;
foreach ($lessons as $lesson) {
    if (!get_post_meta($lesson->ID, '_tutor_course_id_for_lesson', true)) {
        $missing_course_link[] = $lesson->ID;
    }
    if (get_post_meta($lesson->ID, '_tutor_attachments', true)) {
        $with_attachments++;
    }
    if (get_post_meta($lesson->ID, '_video', true)) {
        $with_video++;
    }
}

$enrollments = get_posts([
    'post_type'   => 'tutor_enrolled',
    'post_parent' => $course_id,
    'post_status' => 'any',
    'numberposts' => -1,
    'fields'      => 'ids',
]);

$options = get_option('tutor_option');

WP_CLI::log('Course: ' . $course->post_title . " (#{$course_id})");
WP_CLI::log('Status: ' . $course->post_status);
WP_CLI::log('Public: ' . (get_post_meta($course_id, '_tutor_is_public_course', true) ?: 'no'));
WP_CLI::log('Topics: ' . count($topics));
WP_CLI::log('Lessons: ' . count($lessons));
WP_CLI::log('Quizzes: ' . count($quizzes));
WP_CLI::log('Assignments: ' . count($assignments));
WP_CLI::log('Lessons with attachments: ' . $with_attachments);
WP_CLI::log('Lessons with video: ' . $with_video);
WP_CLI::log('Lessons missing course link: ' . (count($missing_course_link) ? implode(', ', $missing_course_link) : 'none'));
WP_CLI::log('Enrollments: ' . count($enrollments));

$prerequisites = get_post_meta($course_id, '_tutor_course_prerequisites_ids', true);
WP_CLI::log('Prerequisites: ' . json_encode($prerequisites ?: []));
WP_CLI::log('Duration: ' . json_encode(get_post_meta($course_id, '_course_duration', true)));
WP_CLI::log('Certificate template: ' . (get_post_meta($course_id, 'tutor_course_certificate_template', true) ?: 'none'));
WP_CLI::log('Product ID: ' . (get_post_meta($course_id, '_tutor_course_product_id', true) ?: 'none'));
WP_CLI::log('Completion mode: ' . ($options['course_completion_process'] ?? '?'));

$warnings = [];
if (count($missing_course_link)) {
    $warnings[] = 'Some lessons will not count toward progress (missing _tutor_course_id_for_lesson).';
}
if (!$quizzes && !$assignments) {
    $warnings[] = 'No quiz or assignment: the course has no graded check.';
}
if (!$with_attachments) {
    $warnings[] = 'No lesson has attachments; any "download" promise is empty.';
}
if ('strict' === ($options['course_completion_process'] ?? '')) {
    $warnings[] = 'Completion is strict: certificates wait for instructor grading.';
}
if ($prerequisites && !empty($options['tutor_pro_prerequisites_redirect'])) {
    $warnings[] = 'Prerequisites are a hard gate; verify the student can access the content.';
}

if ($warnings) {
    WP_CLI::warning(implode(' ', $warnings));
} else {
    WP_CLI::success('Course checks passed.');
}
