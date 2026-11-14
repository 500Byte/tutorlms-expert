<?php
/**
 * Idempotent Tutor LMS curriculum importer.
 *
 * Usage:
 *   wp eval-file create-course-structure.php structure.json
 *
 * structure.json:
 * {
 *   "123": [
 *     {"title": "Tema 1: Fundamentos", "lessons": ["1.1 ...", "1.2 ..."]},
 *     {"title": "Tema 2: Estrategia", "lessons": ["2.1 ..."]}
 *   ]
 * }
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$json_path = isset($args[0]) ? $args[0] : '';
if (!$json_path || !file_exists($json_path)) {
    WP_CLI::error('Usage: wp eval-file create-course-structure.php <structure.json>');
}

$structure = json_decode(file_get_contents($json_path), true);
if (!is_array($structure)) {
    WP_CLI::error('Invalid JSON: ' . $json_path);
}

if (!function_exists('tlx_ensure_post')) {
    function tlx_ensure_post(array $args) {
        $existing = get_posts([
            'post_type'   => $args['post_type'],
            'post_parent' => $args['post_parent'],
            'title'       => $args['post_title'],
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ]);

        if ($existing) {
            $args['ID'] = (int) $existing[0];
            wp_update_post($args);
            return (int) $existing[0];
        }

        return (int) wp_insert_post($args);
    }
}

$topics_created = 0;
$lessons_created = 0;

foreach ($structure as $course_id => $topics) {
    $course_id = (int) $course_id;
    $course = get_post($course_id);
    if (!$course || 'courses' !== $course->post_type) {
        WP_CLI::warning("Skipping {$course_id}: not a course.");
        continue;
    }

    foreach ($topics as $topic_index => $topic) {
        $topic_id = tlx_ensure_post([
            'post_type'   => 'topics',
            'post_title'  => $topic['title'],
            'post_parent' => $course_id,
            'menu_order'  => $topic_index + 1,
            'post_status' => 'publish',
        ]);
        $topics_created++;

        foreach ($topic['lessons'] as $lesson_index => $lesson_title) {
            $lesson_id = tlx_ensure_post([
                'post_type'   => 'lesson',
                'post_title'  => $lesson_title,
                'post_parent' => $topic_id,
                'menu_order'  => $lesson_index,
                'post_status' => 'publish',
            ]);
            update_post_meta($lesson_id, '_tutor_course_id_for_lesson', $course_id);
            $lessons_created++;
        }
    }
}

WP_CLI::success("Topics: {$topics_created} | Lessons: {$lessons_created}");
