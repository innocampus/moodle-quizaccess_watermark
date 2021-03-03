<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Detect watermarks and report.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
$context = context_module::instance($cm->id);
$title = get_string('report', 'quizaccess_watermark');
$url = new moodle_url('/mod/quiz/accessrule/watermark/report.php',
        ['cmid' => $cmid, 'attempt' => $attemptid]);
$PAGE->set_cm($cm, $course);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');


require_login($course);
require_capability('quizaccess/watermark:view_reports', $context);

echo $OUTPUT->header();
$data = [];

if ($attemptid == 0) {
    // Detect watermarks in all attempts.
    $header = [
        get_string('report_attempt', 'quizaccess_watermark'),
        get_string('report_watermark_found_from', 'quizaccess_watermark'),
    ];

    $allhits = quizaccess_watermark\attempt::find_all_users_with_foreign_watermarks($cm->instance);
    foreach ($allhits as $hit) {
        $url = new moodle_url('/mod/quiz/accessrule/watermark/report.php', ['cmid' => $cmid, 'attempt' => $hit['quizattemptid']]);
        $user = html_writer::link($url, $hit['userfullname']);
        $data[] = [
            $user, $hit['watermarksfrom']
        ];
    }

} else {
    $header = [
        get_string('report_answer_time', 'quizaccess_watermark'),
        get_string('report_answer', 'quizaccess_watermark'),
        get_string('report_watermark_found', 'quizaccess_watermark'),
        get_string('report_watermark_found_from', 'quizaccess_watermark'),
    ];

    $wmattempt = quizaccess_watermark\attempt::get_from_quiz_attempt_id($attemptid);
    if ($wmattempt->get_quiz_id() != $cm->instance) {
        throw new \moodle_exception('quiz_attempt_id_mismatch', 'quizaccess_watermark');
    }

    echo html_writer::tag('h4', $wmattempt->get_user_fullname());
    echo get_string('report_user_watermark', 'quizaccess_watermark', $wmattempt->get_hash());
    echo '<br><br>';

    $hits = $wmattempt->find_foreign_watermarks();
    foreach ($hits as $hit) {
        if ($hit['hit']) {
            $url = new moodle_url('/mod/quiz/review.php', ['cmid' => $cmid, 'attempt' => $hit['hit']['quizattemptid']]);
            $user = html_writer::link($url, $hit['hit']['fullname']);
        } else {
            $user = get_string('watermark_unknown', 'quizaccess_watermark');
        }

        $data[] = [
            userdate($hit['date']), htmlspecialchars($hit['answer']),
            $hit['watermark'], $user
        ];
    }
}

$table = new html_table();
$table->class = 'generaltable';
$table->id = 'watermarkreport';
$table->head = $header;
$table->data = $data;
echo html_writer::table($table);

echo $OUTPUT->footer();
