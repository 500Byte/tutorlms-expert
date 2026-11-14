<?php
/**
 * Audit a Tutor LMS course before selling it.
 *
 * Read-only: this script never writes posts, post meta, options or user meta.
 * Safe to run any number of times.
 *
 * Usage:
 *   wp eval-file audit-course.php <course_id> [--format=text|json] [--fail-on=critical|warning|none]
 *
 * Exit codes: 0 always unless --fail-on is given; 2 when the chosen threshold is met.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Append one finding to the report.
 *
 * @param array  $findings Report findings.
 * @param string $severity critical|warning|info.
 * @param string $code     Stable machine code.
 * @param string $message  Human message.
 * @param array  $ids      Related post/object IDs.
 * @return void
 */
function tutor_audit_add( array &$findings, $severity, $code, $message, array $ids = array() ) {
	$findings[] = array(
		'severity' => $severity,
		'code'     => $code,
		'message'  => $message,
		'ids'      => array_values( array_unique( array_map( 'intval', $ids ) ) ),
	);
}

/**
 * Sort weight for a severity.
 *
 * @param string $severity Severity.
 * @return int
 */
function tutor_audit_rank( $severity ) {
	$ranks = array(
		'critical' => 0,
		'warning'  => 1,
		'info'     => 2,
	);

	return isset( $ranks[ $severity ] ) ? $ranks[ $severity ] : 3;
}

/**
 * Normalize a meta value into a list of integer IDs.
 *
 * @param mixed $value Array, comma/space separated string or scalar.
 * @return int[]
 */
function tutor_audit_ids( $value ) {
	$ids = array();

	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) ) {
				$item = $item['id'];
			}
			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			}
		}
	} elseif ( is_numeric( $value ) ) {
		$ids[] = (int) $value;
	} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
		foreach ( preg_split( '/[,\s]+/', $value ) as $item ) {
			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			}
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Whether a Tutor video meta value actually points to a playable source.
 *
 * @param mixed $video `_video` meta value.
 * @return bool
 */
function tutor_audit_has_video( $video ) {
	if ( ! is_array( $video ) ) {
		return false;
	}

	$source = isset( $video['source'] ) ? (string) $video['source'] : '';
	if ( '' === $source ) {
		return false;
	}

	if ( 'html5' === $source ) {
		return ! empty( $video['source_video_id'] ) || ! empty( $video['source_html5'] );
	}

	return ! empty( $video['source_video_id'] ) || ! empty( $video['source_external_url'] );
}

$tutor_audit_args      = isset( $args ) && is_array( $args ) ? $args : array();
$tutor_audit_course_id = 0;
$tutor_audit_format    = 'text';
$tutor_audit_fail_on   = 'none';

foreach ( $tutor_audit_args as $tutor_audit_arg ) {
	if ( 0 === strpos( $tutor_audit_arg, '--format=' ) ) {
		$tutor_audit_format = substr( $tutor_audit_arg, 9 );
	} elseif ( 0 === strpos( $tutor_audit_arg, '--fail-on=' ) ) {
		$tutor_audit_fail_on = substr( $tutor_audit_arg, 10 );
	} elseif ( is_numeric( $tutor_audit_arg ) ) {
		$tutor_audit_course_id = (int) $tutor_audit_arg;
	}
}

if ( ! in_array( $tutor_audit_format, array( 'text', 'json' ), true ) ) {
	WP_CLI::error( 'Invalid --format. Use text or json.' );
}
if ( ! in_array( $tutor_audit_fail_on, array( 'none', 'critical', 'warning' ), true ) ) {
	WP_CLI::error( 'Invalid --fail-on. Use none, critical or warning.' );
}
if ( ! $tutor_audit_course_id ) {
	WP_CLI::error( 'Usage: wp eval-file audit-course.php <course_id> [--format=text|json] [--fail-on=critical|warning|none]' );
}

$course = get_post( $tutor_audit_course_id );
if ( ! $course || 'courses' !== $course->post_type ) {
	WP_CLI::error( sprintf( 'Course %d not found or is not a Tutor course.', $tutor_audit_course_id ) );
}

global $wpdb;

$findings         = array();
$statuses         = array( 'publish', 'private', 'draft', 'pending', 'future', 'trash' );
$course_id        = (int) $course->ID;
$courses_post     = 'courses';
$questions_table  = $wpdb->prefix . 'tutor_quiz_questions';
$answers_table    = $wpdb->prefix . 'tutor_quiz_question_answers';
$has_quiz_tables  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $questions_table ) ) === $questions_table )
	&& ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $answers_table ) ) === $answers_table );

$tutor_option = get_option( 'tutor_option' );
if ( ! is_array( $tutor_option ) ) {
	$tutor_option = array();
}

$topics = get_posts(
	array(
		'post_type'      => 'topics',
		'post_parent'    => $course_id,
		'post_status'    => $statuses,
		'numberposts'    => -1,
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	)
);

$lessons     = array();
$quizzes     = array();
$assignments = array();
$structure   = array();

foreach ( $topics as $topic ) {
	$topic_lessons = get_posts(
		array(
			'post_type'      => 'lesson',
			'post_parent'    => $topic->ID,
			'post_status'    => $statuses,
			'numberposts'    => -1,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);
	$topic_quizzes = get_posts(
		array(
			'post_type'      => 'tutor_quiz',
			'post_parent'    => $topic->ID,
			'post_status'    => $statuses,
			'numberposts'    => -1,
			'posts_per_page' => -1,
		)
	);
	$topic_assignments = get_posts(
		array(
			'post_type'      => 'tutor_assignments',
			'post_parent'    => $topic->ID,
			'post_status'    => $statuses,
			'numberposts'    => -1,
			'posts_per_page' => -1,
		)
	);

	$lessons     = array_merge( $lessons, $topic_lessons );
	$quizzes     = array_merge( $quizzes, $topic_quizzes );
	$assignments = array_merge( $assignments, $topic_assignments );

	$structure[] = array(
		'id'          => (int) $topic->ID,
		'title'       => $topic->post_title,
		'status'      => $topic->post_status,
		'menu_order'  => (int) $topic->menu_order,
		'lessons'     => count( $topic_lessons ),
		'quizzes'     => count( $topic_quizzes ),
		'assignments' => count( $topic_assignments ),
	);

	if ( 0 === count( $topic_lessons ) + count( $topic_quizzes ) + count( $topic_assignments ) ) {
		tutor_audit_add( $findings, 'warning', 'empty-topic', sprintf( 'Topic "%s" (#%d) has no lessons, quizzes or assignments.', $topic->post_title, $topic->ID ), array( $topic->ID ) );
	}
}

$topic_ids      = array_map( 'intval', wp_list_pluck( $topics, 'ID' ) );
$lesson_ids     = array_map( 'intval', wp_list_pluck( $lessons, 'ID' ) );
$assignment_ids = array_map( 'intval', wp_list_pluck( $assignments, 'ID' ) );

if ( 0 === count( $topics ) ) {
	tutor_audit_add( $findings, 'critical', 'no-topics', 'The course has no topics: the curriculum is empty.', array( $course_id ) );
}
if ( 0 === count( $lessons ) ) {
	tutor_audit_add( $findings, 'critical', 'no-lessons', 'The course has no lessons.', array( $course_id ) );
}

$order_map = array();
foreach ( $topics as $topic ) {
	$order_map[ (int) $topic->menu_order ][] = (int) $topic->ID;
}
foreach ( $order_map as $order => $ids ) {
	if ( count( $ids ) > 1 ) {
		tutor_audit_add( $findings, 'warning', 'topic-order-duplicate', sprintf( 'Topics %s share menu_order %d; next/previous may be unpredictable.', implode( ', ', $ids ), $order ), $ids );
	}
}

$lessons_without_link = array();
$lessons_without_video = array();
$lessons_with_files    = 0;

foreach ( $lessons as $lesson ) {
	$link    = (int) get_post_meta( $lesson->ID, '_tutor_course_id_for_lesson', true );
	$parent  = (int) wp_get_post_parent_id( $lesson->ID );
	$is_here = $parent && in_array( $parent, $topic_ids, true );

	if ( ! $link ) {
		$lessons_without_link[] = (int) $lesson->ID;
		if ( ! $is_here ) {
			tutor_audit_add( $findings, 'critical', 'lesson-unreachable', sprintf( 'Lesson "%s" (#%d) has no course link and no topic parent inside this course: it is unreachable.', $lesson->post_title, $lesson->ID ), array( $lesson->ID ) );
		}
	} elseif ( $link !== $course_id ) {
		tutor_audit_add( $findings, 'warning', 'lesson-link-stale', sprintf( 'Lesson "%s" (#%d) points to course #%d while living under course #%d.', $lesson->post_title, $lesson->ID, $link, $course_id ), array( $lesson->ID ) );
	}

	$video = get_post_meta( $lesson->ID, '_video', true );
	if ( ! tutor_audit_has_video( $video ) ) {
		$lessons_without_video[] = (int) $lesson->ID;
	}

	$attachments = tutor_audit_ids( get_post_meta( $lesson->ID, '_tutor_attachments', true ) );
	if ( $attachments ) {
		$lessons_with_files++;
		foreach ( $attachments as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
					tutor_audit_add( $findings, 'warning', 'attachment-missing', sprintf( 'Lesson "%s" (#%d) references attachment #%d which does not exist.', $lesson->post_title, $lesson->ID, $attachment_id ), array( $lesson->ID, $attachment_id ) );
			}
		}
	}

	$has_body = '' !== trim( wp_strip_all_tags( (string) $lesson->post_content ) );
	if ( ! $has_body && ! tutor_audit_has_video( $video ) && ! $attachments ) {
		tutor_audit_add( $findings, 'warning', 'empty-lesson', sprintf( 'Lesson "%s" (#%d) has no text, video or attachments.', $lesson->post_title, $lesson->ID ), array( $lesson->ID ) );
	}
}

if ( $lessons_without_link && count( $lessons_without_link ) === count( $lessons ) ) {
	tutor_audit_add( $findings, 'warning', 'lesson-link-all-missing', sprintf( 'No lesson has _tutor_course_id_for_lesson (%s). Tutor falls back to the topic parent, but addons and reports that query the meta will miss them.', implode( ', ', $lessons_without_link ) ), $lessons_without_link );
} elseif ( $lessons_without_link ) {
	tutor_audit_add( $findings, 'info', 'lesson-link-missing', sprintf( '%d lesson(s) have no _tutor_course_id_for_lesson: %s. Topic parent resolves them, but the meta is the compatibility fallback.', count( $lessons_without_link ), implode( ', ', $lessons_without_link ) ), $lessons_without_link );
}

if ( $lessons && count( $lessons_without_video ) === count( $lessons ) ) {
	tutor_audit_add( $findings, 'warning', 'video-all-missing', 'No lesson has a playable video. If the course is sold as video training, the value proposition is empty.', $lessons_without_video );
} elseif ( $lessons_without_video ) {
	tutor_audit_add( $findings, 'info', 'video-missing', sprintf( '%d of %d lesson(s) have no playable video: %s.', count( $lessons_without_video ), count( $lessons ), implode( ', ', $lessons_without_video ) ), $lessons_without_video );
}

$linked_lessons = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tutor_course_id_for_lesson' AND meta_value = %d", $course_id ) );
foreach ( array_diff( array_map( 'intval', (array) $linked_lessons ), $lesson_ids ) as $orphan_id ) {
	tutor_audit_add( $findings, 'critical', 'lesson-orphan', sprintf( 'Lesson #%d claims this course but is not under any of its topics.', $orphan_id ), array( $orphan_id ) );
}

if ( ! $has_quiz_tables ) {
	tutor_audit_add( $findings, 'critical', 'quiz-tables-missing', 'Tutor quiz tables are missing. The plugin is not fully installed or the prefix is wrong.', array() );
}

foreach ( $quizzes as $quiz ) {
	$question_count = 0;
	$mark_total     = 0.0;

	if ( $has_quiz_tables ) {
		$question_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$questions_table} WHERE quiz_id = %d", $quiz->ID ) );
		$mark_total     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(question_mark), 0) FROM {$questions_table} WHERE quiz_id = %d", $quiz->ID ) );

		$question_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.question_id, q.question_type, COUNT(a.answer_id) AS answers
				FROM {$questions_table} q
				LEFT JOIN {$answers_table} a ON a.belongs_question_id = q.question_id
				WHERE q.quiz_id = %d
				GROUP BY q.question_id, q.question_type",
				$quiz->ID
			),
			ARRAY_A
		);

		$needs_answers = array( 'multiple_choice', 'true_false', 'image_answering', 'matching', 'ordering' );
		foreach ( (array) $question_rows as $row ) {
			if ( in_array( $row['question_type'], $needs_answers, true ) && (int) $row['answers'] < 2 ) {
				tutor_audit_add( $findings, 'warning', 'question-without-answers', sprintf( 'Quiz "%s" (#%d), question #%d (%s) has fewer than two answer options.', $quiz->post_title, $quiz->ID, $row['question_id'], $row['question_type'] ), array( $quiz->ID, (int) $row['question_id'] ) );
			}
		}
	}

	if ( $has_quiz_tables && 0 === $question_count ) {
		tutor_audit_add( $findings, 'critical', 'quiz-without-questions', sprintf( 'Quiz "%s" (#%d) has no questions and cannot be taken.', $quiz->post_title, $quiz->ID ), array( $quiz->ID ) );
	}

	if ( $question_count > 0 && $mark_total <= 0 ) {
		tutor_audit_add( $findings, 'warning', 'quiz-without-marks', sprintf( 'Quiz "%s" (#%d) has questions but no marks; passing grade cannot be computed.', $quiz->post_title, $quiz->ID ), array( $quiz->ID ) );
	}

	$quiz_option  = get_post_meta( $quiz->ID, 'tutor_quiz_option', true );
	$passing      = is_array( $quiz_option ) && isset( $quiz_option['passing_grade'] ) ? $quiz_option['passing_grade'] : null;
	if ( null === $passing || '' === $passing ) {
		tutor_audit_add( $findings, 'warning', 'quiz-passing-grade-missing', sprintf( 'Quiz "%s" (#%d) has no passing grade.', $quiz->post_title, $quiz->ID ), array( $quiz->ID ) );
	} elseif ( ! is_numeric( $passing ) || $passing < 0 || $passing > 100 ) {
		tutor_audit_add( $findings, 'warning', 'quiz-passing-grade-range', sprintf( 'Quiz "%s" (#%d) passing grade is %s, outside 0-100.', $quiz->post_title, $quiz->ID, $passing ), array( $quiz->ID ) );
	}
}

foreach ( $assignments as $assignment ) {
	$assignment_course = (int) get_post_meta( $assignment->ID, '_tutor_course_id_for_assignments', true );
	$parent            = (int) wp_get_post_parent_id( $assignment->ID );
	$is_here           = $parent && in_array( $parent, $topic_ids, true );

	if ( ! $assignment_course ) {
		$severity                     = $is_here ? 'warning' : 'critical';
		tutor_audit_add( $findings, $severity, 'assignment-without-course', sprintf( 'Assignment "%s" (#%d) has no _tutor_course_id_for_assignments.', $assignment->post_title, $assignment->ID ), array( $assignment->ID ) );
	} elseif ( $assignment_course !== $course_id ) {
		tutor_audit_add( $findings, 'warning', 'assignment-course-stale', sprintf( 'Assignment "%s" (#%d) points to course #%d while living under course #%d.', $assignment->post_title, $assignment->ID, $assignment_course, $course_id ), array( $assignment->ID ) );
	}

	$option    = get_post_meta( $assignment->ID, 'assignment_option', true );
	$total     = is_array( $option ) && isset( $option['total_mark'] ) ? (float) $option['total_mark'] : 0.0;
	$pass_mark = is_array( $option ) && isset( $option['pass_mark'] ) ? (float) $option['pass_mark'] : 0.0;

	if ( $total <= 0 ) {
		tutor_audit_add( $findings, 'warning', 'assignment-marks-missing', sprintf( 'Assignment "%s" (#%d) has no total mark.', $assignment->post_title, $assignment->ID ), array( $assignment->ID ) );
	} elseif ( $pass_mark > $total ) {
		tutor_audit_add( $findings, 'warning', 'assignment-pass-above-total', sprintf( 'Assignment "%s" (#%d) pass mark (%s) is above total mark (%s).', $assignment->post_title, $assignment->ID, $pass_mark, $total ), array( $assignment->ID ) );
	}
}

$linked_assignments = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tutor_course_id_for_assignments' AND meta_value = %d", $course_id ) );
foreach ( array_diff( array_map( 'intval', (array) $linked_assignments ), $assignment_ids ) as $orphan_id ) {
	tutor_audit_add( $findings, 'critical', 'assignment-orphan', sprintf( 'Assignment #%d claims this course but is not under any of its topics.', $orphan_id ), array( $orphan_id ) );
}

$certificate_template = (string) get_post_meta( $course_id, 'tutor_course_certificate_template', true );
if ( '' === $certificate_template ) {
	tutor_audit_add( $findings, 'warning', 'certificate-missing', 'The course has no certificate template. If the offer promises a diploma, it cannot be issued.', array( $course_id ) );
} else {
	if ( isset( $tutor_option['send_certificate_link_to_course_completion_email'] ) && 'on' !== $tutor_option['send_certificate_link_to_course_completion_email'] ) {
		tutor_audit_add( $findings, 'info', 'certificate-email-off', 'Certificate link is not included in the course completion email.', array( $course_id ) );
	}
}

$price_type  = (string) get_post_meta( $course_id, '_tutor_course_price_type', true );
$product_id  = (int) get_post_meta( $course_id, '_tutor_course_product_id', true );
$monetize_by = isset( $tutor_option['monetize_by'] ) ? (string) $tutor_option['monetize_by'] : 'free';
$selling     = (string) get_post_meta( $course_id, 'tutor_course_selling_option', true );
$native      = (float) get_post_meta( $course_id, 'tutor_course_price', true );
$native_sale = (float) get_post_meta( $course_id, 'tutor_course_sale_price', true );
$legacy      = (float) get_post_meta( $course_id, '_tutor_course_price', true );
$legacy_sale = (float) get_post_meta( $course_id, '_tutor_course_sale_price', true );

if ( '' === $price_type ) {
	tutor_audit_add( $findings, 'warning', 'price-type-missing', 'Course has no _tutor_course_price_type (free, paid or subscription).', array( $course_id ) );
}

if ( in_array( $price_type, array( 'paid', 'subscription' ), true ) ) {
	if ( 'wc' === $monetize_by ) {
		if ( ! $product_id ) {
			tutor_audit_add( $findings, 'critical', 'product-missing', 'Paid course with WooCommerce monetization has no _tutor_course_product_id.', array( $course_id ) );
		} elseif ( ! function_exists( 'wc_get_product' ) ) {
			tutor_audit_add( $findings, 'critical', 'woocommerce-inactive', 'The course is monetized with WooCommerce but WooCommerce is not active.', array( $course_id ) );
		} else {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				tutor_audit_add( $findings, 'critical', 'product-not-found', sprintf( 'WooCommerce product #%d linked to the course does not exist.', $product_id ), array( $course_id, $product_id ) );
			} else {
				if ( 'publish' !== $product->get_status() ) {
					tutor_audit_add( $findings, 'warning', 'product-not-published', sprintf( 'WooCommerce product #%d is "%s", not publish.', $product_id, $product->get_status() ), array( $product_id ) );
				}
				if ( (float) $product->get_price() <= 0 ) {
					tutor_audit_add( $findings, 'critical', 'product-price-zero', sprintf( 'WooCommerce product #%d has a price of 0.', $product_id ), array( $product_id ) );
				}
			}
		}
	} elseif ( 'tutor' === $monetize_by ) {
		if ( $native <= 0 && $native_sale <= 0 && $legacy <= 0 && $legacy_sale <= 0 ) {
			tutor_audit_add( $findings, 'critical', 'native-price-missing', 'Paid course with native eCommerce has no price. Checked tutor_course_price and legacy _tutor_course_price.', array( $course_id ) );
		} elseif ( $native <= 0 && $legacy > 0 ) {
			tutor_audit_add( $findings, 'warning', 'native-price-legacy-key', sprintf( 'Course price lives only in legacy _tutor_course_price (%s). Tutor 4.x native eCommerce reads tutor_course_price.', $legacy ), array( $course_id ) );
		}
		if ( $native > 0 && $native_sale > 0 && $native_sale >= $native ) {
			tutor_audit_add( $findings, 'warning', 'native-sale-above-regular', sprintf( 'Sale price (%s) is not below regular price (%s).', $native_sale, $native ), array( $course_id ) );
		}
	} elseif ( 'edd' === $monetize_by ) {
		if ( ! $product_id ) {
			tutor_audit_add( $findings, 'critical', 'edd-product-missing', 'Paid course with EDD monetization has no _tutor_course_product_id.', array( $course_id ) );
		} else {
			$edd_price = (float) get_post_meta( $product_id, 'edd_price', true );
			if ( $edd_price <= 0 ) {
				tutor_audit_add( $findings, 'critical', 'edd-price-missing', sprintf( 'EDD download #%d has no price.', $product_id ), array( $product_id ) );
			}
		}
	} elseif ( 'free' === $monetize_by ) {
		tutor_audit_add( $findings, 'critical', 'monetization-disabled', 'Course is paid but monetization is disabled (monetize_by=free). Nobody can buy it.', array( $course_id ) );
	}

	if ( 'wc' === $monetize_by && $product_id && in_array( $selling, array( 'subscription', 'both', 'membership', 'all' ), true ) ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( $product && ! $product->is_type( 'subscription' ) && ! $product->is_type( 'variable-subscription' ) ) {
			tutor_audit_add( $findings, 'warning', 'subscription-product-mismatch', sprintf( 'Selling option is "%s" but product #%d is not a subscription.', $selling, $product_id ), array( $product_id ) );
		}
	}
} elseif ( 'free' === $price_type && $product_id ) {
	tutor_audit_add( $findings, 'info', 'free-with-product', sprintf( 'Course is free but still links WooCommerce product #%d.', $product_id ), array( $product_id ) );
}

if ( 'publish' !== $course->post_status ) {
	tutor_audit_add( $findings, 'warning', 'not-published', sprintf( 'Course status is "%s"; it is not for sale.', $course->post_status ), array( $course_id ) );
}
if ( '' !== (string) $course->post_password ) {
	tutor_audit_add( $findings, 'warning', 'password-protected', 'Course is password protected; buyers cannot open it until they have the password.', array( $course_id ) );
}
if ( 'yes' === get_post_meta( $course_id, '_tutor_is_public_course', true ) ) {
	tutor_audit_add( $findings, 'info', 'public-course', 'Course is public: all lessons are readable without enrollment.', array( $course_id ) );
}
if ( ! get_post_thumbnail_id( $course_id ) ) {
	tutor_audit_add( $findings, 'info', 'thumbnail-missing', 'Course has no featured image.', array( $course_id ) );
}
$course_categories = wp_get_post_terms( $course_id, 'course-category', array( 'fields' => 'ids' ) );
if ( is_wp_error( $course_categories ) || ! $course_categories ) {
	tutor_audit_add( $findings, 'info', 'category-missing', 'Course has no course category.', array( $course_id ) );
}

$duplicate_courses = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND ID <> %d AND post_status <> 'trash' ORDER BY ID ASC",
		$courses_post,
		$course->post_title,
		$course_id
	)
);
if ( $duplicate_courses ) {
	tutor_audit_add( $findings, 'warning', 'course-title-duplicate', sprintf( 'Another course uses the title "%s": %s.', $course->post_title, implode( ', ', $duplicate_courses ) ), array_merge( array( $course_id ), array_map( 'intval', $duplicate_courses ) ) );
}

$lesson_titles = array();
foreach ( $lessons as $lesson ) {
	$lesson_titles[ $lesson->post_title ][] = (int) $lesson->ID;
}
foreach ( $lesson_titles as $title => $ids ) {
	if ( count( $ids ) > 1 ) {
		tutor_audit_add( $findings, 'info', 'lesson-title-duplicate', sprintf( 'Lesson title "%s" is repeated: %s.', $title, implode( ', ', $ids ) ), $ids );
	}
}

$enrollment_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_status, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_parent = %d GROUP BY post_status",
		$course_id
	),
	ARRAY_A
);
$enrollments = array();
foreach ( (array) $enrollment_rows as $row ) {
	$enrollments[ $row['post_status'] ] = (int) $row['total'];
}
$completed_enrollments = isset( $enrollments['completed'] ) ? $enrollments['completed'] : 0;

if ( preg_match( '/\b(demo|sample|ejemplo|prueba|test)\b/i', $course->post_title ) && 0 === $completed_enrollments ) {
	tutor_audit_add( $findings, 'warning', 'demo-course', 'Course looks like leftover demo content and has no completed enrollments.', array( $course_id ) );
}

if ( '' === (string) get_option( 'timezone_string' ) ) {
	tutor_audit_add( $findings, 'warning', 'timezone-unset', 'WordPress timezone_string is empty; dates and deadlines may drift with DST.', array() );
}

$email_from = isset( $tutor_option['email_from_address'] ) ? (string) $tutor_option['email_from_address'] : '';
if ( '' === $email_from ) {
	tutor_audit_add( $findings, 'info', 'email-from-empty', 'Tutor email_from_address is empty; verify SMTP and the sender before selling.', array() );
} elseif ( ! is_email( $email_from ) ) {
	tutor_audit_add( $findings, 'warning', 'email-from-invalid', sprintf( 'Tutor email_from_address "%s" is not a valid email.', $email_from ), array() );
}

$has_content = count( $lessons ) + count( $quizzes ) + count( $assignments );
if ( $has_content > 0 && count( $lessons ) < count( $topics ) ) {
	tutor_audit_add( $findings, 'info', 'thin-curriculum', 'There are fewer lessons than topics; verify the course is not half-built.', array( $course_id ) );
}

usort(
	$findings,
	function ( $a, $b ) {
		return tutor_audit_rank( $a['severity'] ) - tutor_audit_rank( $b['severity'] );
	}
);

$summary = array(
	'critical' => 0,
	'warning'  => 0,
	'info'     => 0,
);
foreach ( $findings as $finding ) {
	$summary[ $finding['severity'] ]++;
}

if ( 'json' === $tutor_audit_format ) {
	$payload = array(
		'schema'  => 'tutorlms-course-audit/v1',
		'course'  => array(
			'id'       => $course_id,
			'title'    => $course->post_title,
			'status'   => $course->post_status,
			'author'   => (int) $course->post_author,
			'modified' => $course->post_modified_gmt,
		),
		'config'  => array(
			'monetize_by'          => $monetize_by,
			'price_type'           => $price_type,
			'product_id'           => $product_id,
			'selling_option'       => $selling,
			'is_public'            => get_post_meta( $course_id, '_tutor_is_public_course', true ),
			'certificate_template' => $certificate_template,
			'timezone_string'      => get_option( 'timezone_string' ),
		),
		'counts'  => array(
			'topics'           => count( $topics ),
			'lessons'          => count( $lessons ),
			'quizzes'          => count( $quizzes ),
			'assignments'      => count( $assignments ),
			'lessons_with_video' => count( $lessons ) - count( $lessons_without_video ),
			'lessons_with_files' => $lessons_with_files,
			'enrollments'      => $enrollments,
		),
		'structure' => $structure,
		'summary' => $summary,
		'findings' => $findings,
	);

	WP_CLI::line( wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
} else {
	WP_CLI::log( sprintf( 'Tutor LMS course audit — %s (#%d)', $course->post_title, $course_id ) );
	WP_CLI::log( sprintf( 'Status: %s | Price: %s (%s, product %s) | Public: %s', $course->post_status, $price_type ? $price_type : 'unset', $monetize_by, $product_id ? $product_id : 'none', get_post_meta( $course_id, '_tutor_is_public_course', true ) ?: 'no' ) );
	WP_CLI::log( sprintf( 'Curriculum: %d topics | %d lessons | %d quizzes | %d assignments', count( $topics ), count( $lessons ), count( $quizzes ), count( $assignments ) ) );
	WP_CLI::log( sprintf( 'Media: %d lessons with video | %d lessons with attachments', count( $lessons ) - count( $lessons_without_video ), $lessons_with_files ) );
	WP_CLI::log( sprintf( 'Enrollments: %s', $enrollments ? wp_json_encode( $enrollments ) : 'none' ) );
	WP_CLI::log( '' );

	foreach ( $findings as $finding ) {
		WP_CLI::log( sprintf( '[%s] %s — %s', strtoupper( $finding['severity'] ), $finding['code'], $finding['message'] ) );
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Summary: %d critical, %d warning, %d info', $summary['critical'], $summary['warning'], $summary['info'] ) );
}

if ( 'critical' === $tutor_audit_fail_on && $summary['critical'] > 0 ) {
	WP_CLI::halt( 2 );
}
if ( 'warning' === $tutor_audit_fail_on && ( $summary['critical'] > 0 || $summary['warning'] > 0 ) ) {
	WP_CLI::halt( 2 );
}
