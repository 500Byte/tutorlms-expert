#!/usr/bin/env bash
# Read-only Tutor LMS inventory.
# Usage: ./tutor-inspect.sh /path/to/wordpress
set -euo pipefail

WP_PATH="${1:-.}"
WP=(wp --path="$WP_PATH")

# version_ge A B: true when A >= B (GNU version sort).
version_ge() {
    local lower
    lower="$(printf '%s\n%s\n' "$2" "$1" | sort -V)"
    [ "${lower%%$'\n'*}" = "$2" ]
}

echo "== Site =="
"${WP[@]}" option get siteurl

echo
echo "== Versions =="
tutor_version="$("${WP[@]}" plugin get tutor --field=version 2>/dev/null || true)"
if [ -n "$tutor_version" ]; then
    echo "tutor=$tutor_version"
else
    echo "tutor=unknown"
fi
tutor_pro_version="$("${WP[@]}" plugin get tutor-pro --field=version 2>/dev/null || true)"
if [ -n "$tutor_pro_version" ]; then
    echo "tutor-pro=$tutor_pro_version"
else
    echo "tutor-pro=n/a"
fi

echo
echo "== Tutor v4 detection =="
if [ -z "$tutor_version" ]; then
    echo "unknown"
elif version_ge "$tutor_version" "4.0"; then
    echo "v4"
else
    echo "pre-v4"
fi

echo
echo "== REST API token auth gate =="
if [ -z "$tutor_version" ]; then
    echo "token_auth=unknown"
elif version_ge "$tutor_version" "4.2"; then
    echo "token_auth=available (tutor>=4.2)"
else
    echo "token_auth=unavailable (tutor<4.2)"
fi

echo
echo "== Ecommerce engine =="
"${WP[@]}" eval '
$matches = [];
$opt = get_option("tutor_option");
if (is_array($opt)) {
    foreach ($opt as $key => $value) {
        if (preg_match("/monetiz|ecommerce|engine/i", (string) $key)) {
            $matches["tutor_option[" . $key . "]"] = $value;
        }
    }
}
$wpdb = $GLOBALS["wpdb"];
$rows = $wpdb->get_results("
    SELECT option_name
    FROM {$wpdb->options}
    WHERE option_name LIKE \"%tutor%\"
      AND (option_name LIKE \"%monetiz%\" OR option_name LIKE \"%ecommerce%\" OR option_name LIKE \"%engine%\")
");
foreach ($rows as $row) {
    if ($row->option_name === "tutor_option") { continue; }
    $matches[$row->option_name] = get_option($row->option_name);
}
if (empty($matches)) {
    echo "engine=unknown\n";
} else {
    ksort($matches);
    foreach ($matches as $label => $value) {
        echo $label . "=" . json_encode($value) . "\n";
    }
}
'

echo
echo "== Active addons =="
"${WP[@]}" eval '
$config = get_option("tutor_addons_config");
if (!is_array($config)) { echo "unknown\n"; exit; }
$enabled = [];
foreach ($config as $file => $data) {
    $state = isset($data["is_enable"]) ? $data["is_enable"] : null;
    if (is_scalar($state) && filter_var($state, FILTER_VALIDATE_BOOLEAN)) {
        $enabled[] = basename(dirname($file));
    }
}
$enabled = array_values(array_unique($enabled));
sort($enabled);
if (empty($enabled)) { echo "none\n"; exit; }
foreach ($enabled as $slug) { echo $slug . "\n"; }
'

echo
echo "== Certificate builder =="
cert_plugins="$("${WP[@]}" plugin list --fields=name,status --format=csv 2>/dev/null | grep -i certificate || true)"
if [ -n "$cert_plugins" ]; then
    echo "$cert_plugins"
else
    echo "certificate_plugin=not-found"
fi
"${WP[@]}" eval '
$config = get_option("tutor_addons_config");
if (!is_array($config)) { echo "certificate_addon=unknown\n"; exit; }
$found = false;
foreach ($config as $file => $data) {
    $slug = basename(dirname($file));
    if (stripos($slug, "certificate") === false && stripos($file, "certificate") === false) { continue; }
    $state = isset($data["is_enable"]) ? $data["is_enable"] : null;
    echo "certificate_addon[" . $slug . "]=" . json_encode($state) . "\n";
    $found = true;
}
if (!$found) { echo "certificate_addon=not-found\n"; }
'

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
echo "== Lessons without a resolvable course (meta or topic parent) =="
"${WP[@]}" eval '
$wpdb = $GLOBALS["wpdb"];
$rows = $wpdb->get_results("
    SELECT ID, post_title, post_parent
    FROM {$wpdb->posts}
    WHERE post_type = \"lesson\"
");
$unresolved = [];
foreach ($rows as $row) {
    $course_id = 0;
    $meta_id = (int) get_post_meta($row->ID, "_tutor_course_id_for_lesson", true);
    if ($meta_id > 0) {
        $meta_post = get_post($meta_id);
        if ($meta_post && $meta_post->post_type === "courses") {
            $course_id = (int) $meta_post->ID;
        }
    }
    if (!$course_id && $row->post_parent) {
        $topic = get_post($row->post_parent);
        if ($topic && $topic->post_type === "topics" && $topic->post_parent) {
            $course = get_post($topic->post_parent);
            if ($course && $course->post_type === "courses") {
                $course_id = (int) $course->ID;
            }
        }
    }
    if (!$course_id) {
        $unresolved[] = $row;
    }
}
echo sprintf("lessons scanned: %d | unresolved: %d\n", count($rows), count($unresolved));
foreach ($unresolved as $row) { echo $row->ID . " | " . $row->post_title . "\n"; }
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
