<?php
/**
 * Create a QA student and enroll them in a course (idempotent).
 *
 * Usage:
 *   wp eval-file enroll-student.php <course_id> [username] [password] [email] [product_id]
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$course_id = isset($args[0]) ? (int) $args[0] : 0;
if (!$course_id) {
    WP_CLI::error('Usage: wp eval-file enroll-student.php <course_id> [username] [password] [email] [product_id]');
}

$username   = $args[1] ?? 'qa.student';
$password   = $args[2] ?? 'QaStudent-IPT-2026!#';
$email      = $args[3] ?? $username . '@example.com';
$product_id = isset($args[4]) ? (int) $args[4] : 0;

$user_id = username_exists($username);
if (!$user_id) {
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        WP_CLI::error($user_id->get_error_message());
    }
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    WP_CLI::log("User created: {$user_id}");
} else {
    WP_CLI::log("User exists: {$user_id}");
}

$enrollment = get_posts([
    'post_type'   => 'tutor_enrolled',
    'post_parent' => $course_id,
    'author'      => $user_id,
    'post_status' => 'any',
    'numberposts' => 1,
    'fields'      => 'ids',
]);

if ($enrollment) {
    WP_CLI::log('Enrollment exists: ' . $enrollment[0]);
} else {
    $enrollment_id = wp_insert_post([
        'post_type'   => 'tutor_enrolled',
        'post_title'  => 'QA enrollment',
        'post_status' => 'completed',
        'post_parent' => $course_id,
        'post_author' => $user_id,
    ]);
    update_post_meta($enrollment_id, '_tutor_enrolled_by_order_id', 0);
    update_post_meta($enrollment_id, '_tutor_enrolled_by_product_id', $product_id);
    WP_CLI::log("Enrollment created: {$enrollment_id}");
}

WP_CLI::success('is_enrolled: ' . (tutor_utils()->is_enrolled($course_id, $user_id) ? 'yes' : 'no'));
