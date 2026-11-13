#!/usr/bin/env bash
# Read-only Tutor LMS inventory.
# Usage: ./tutor-inspect.sh /path/to/wordpress
set -euo pipefail

WP_PATH="${1:-.}"
WP=(wp --path="$WP_PATH")

echo "== Site =="
"${WP[@]}" option get siteurl

echo
echo "== Courses =="
"${WP[@]}" post list --post_type=courses --fields=ID,post_title,post_status --format=table

echo
echo "== Curriculum counts =="
"${WP[@]}" eval '
$courses = get_posts(["post_type" => "courses", "numberposts" => -1, "post_status" => "any"]);
foreach ($courses as $course) {
    $topics = get_posts(["post_type" => "topics", "post_parent" => $course->ID, "numberposts" => -1, "post_status" => "any"]);
    $lessons = 0;
    foreach ($topics as $topic) {
        $lessons += count(get_posts(["post_type" => "lesson", "post_parent" => $topic->ID, "numberposts" => -1, "post_status" => "any"]));
    }
    $quizzes = count(get_posts(["post_type" => "tutor_quiz", "post_parent" => wp_list_pluck($topics, "ID"), "numberposts" => -1, "post_status" => "any"]));
    $assignments = count(get_posts(["post_type" => "tutor_assignments", "post_parent" => wp_list_pluck($topics, "ID"), "numberposts" => -1, "post_status" => "any"]));
    echo sprintf("%d | %s | topics=%d lessons=%d quizzes=%d assignments=%d\n", $course->ID, $course->post_title, count($topics), $lessons, $quizzes, $assignments);
}
'

echo
echo "== Lessons missing course link =="
"${WP[@]}" eval '
$rows = $GLOBALS["wpdb"]->get_results("
    SELECT l.ID, l.post_title
    FROM {$GLOBALS["wpdb"]->posts} l
    LEFT JOIN {$GLOBALS["wpdb"]->postmeta} pm
        ON pm.post_id = l.ID AND pm.meta_key = \"_tutor_course_id_for_lesson\"
    WHERE l.post_type = \"lesson\" AND pm.meta_value IS NULL
");
foreach ($rows as $row) { echo $row->ID . " | " . $row->post_title . "\n"; }
'

echo
echo "== Attachments =="
"${WP[@]}" eval '
$rows = $GLOBALS["wpdb"]->get_results("
    SELECT post_id, meta_value
    FROM {$GLOBALS["wpdb"]->postmeta}
    WHERE meta_key = \"_tutor_attachments\"
");
foreach ($rows as $row) { echo $row->post_id . " | " . $row->meta_value . "\n"; }
'

echo
echo "== Addons =="
"${WP[@]}" eval '
$config = get_option("tutor_addons_config");
if (!is_array($config)) { echo "no tutor_addons_config\n"; exit; }
foreach ($config as $file => $data) {
    $name = basename(dirname($file));
    $enabled = isset($data["is_enable"]) ? $data["is_enable"] : "?";
    echo $name . " | " . $enabled . "\n";
}
' | sort

echo
echo "== Key options =="
"${WP[@]}" eval '
$keys = [
    "course_completion_process",
    "auto_course_complete_on_all_lesson_completion",
    "control_video_lesson_completion",
    "enable_course_review",
    "enable_course_announcements",
    "enable_lesson_notes",
    "send_certificate_link_to_course_completion_email",
    "show_instructor_name_on_certificate",
    "tutor_pro_show_prerequisites_courses",
    "tutor_pro_prerequisites_redirect",
    "gradebook_scale",
];
$options = get_option("tutor_option");
foreach ($keys as $key) {
    echo $key . "=" . json_encode($options[$key] ?? null) . "\n";
}
'

echo
echo "== Enrollments =="
"${WP[@]}" post list --post_type=tutor_enrolled --fields=ID,post_title,post_parent,post_status --format=table
