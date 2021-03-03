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
 * Manager to implement the watermark.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_watermark;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

/**
 * Manager to implement the watermark.
 *
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * @var bool Already saved POST data of this page call.
     */
    private static $savedpostdata = false;

    /**
     * @var null|bool Watermark is enabled for the quiz on this page.
     */
    private static $watermarkenabled = null;

    /**
     * Check if watermark is enabled for the quiz.
     *
     * @param $usageid
     * @return bool
     */
    private static function watermark_enabled($usageid) {
        if (self::$watermarkenabled === null) {
            global $DB;
            self::$watermarkenabled = $DB->record_exists('quizaccess_watermark_attempt', ['usageid' => $usageid]);
        }
        return self::$watermarkenabled;
    }

    private static function watermark_enabled_quiz($quizid) {
        if (self::$watermarkenabled === null) {
            global $DB;
            $field = $DB->get_field('quizaccess_watermark', 'watermark', ['quizid' => $quizid]);
            self::$watermarkenabled = ($field !== null) ? $field : false;
        }
        return self::$watermarkenabled;
    }

    /**
     * Save the post data of this page call.
     *
     * Since we remove the watermark from the answers
     *
     * @param int $usageid
     * @param $postdata
     */
    public static function save_post_data(int $usageid, $postdata) {
        if (!self::$savedpostdata) {
            self::$savedpostdata = true;
            if (!self::watermark_enabled($usageid)) {
                return;
            }

            if ($postdata === null) {
                $postdata = $_POST;
            }

            $json = [
                'time' => time(),
                'data' => $postdata,
                'sessionid' => session_id(),
            ];
            $record = new \stdClass();
            $record->usageid = $usageid;
            $record->json = json_encode($json, JSON_INVALID_UTF8_IGNORE, 4);

            global $DB;
            $DB->insert_record('quizaccess_watermark_data', $record, false);
        }
    }

    /**
     * Remove the non-printable characters that are used for the watermarks.
     *
     * This is needed to avoid problems when the answer is automatically graded.
     *
     * @param int $usageid question usage id
     * @param array $data
     * @return array
     */
    public static function clean_answer_data(int $usageid, array $data) : array {
        // Check POST because self::watermark_enabled returns false for preview attempts.
        if (isset($_POST['quizaccess_watermark_enable_clean']) || self::watermark_enabled($usageid)) {
            $new = [];
            foreach ($data as $key => $value) {
                $replaced = preg_replace('/[\x{2060}-\x{2064}\x{E0060}-\x{E007F}]/u', '', $value);
                $new[$key] = ($replaced !== null) ? $replaced : $value; // Keep value as it is when an error occurs.
            }
            return $new;
        } else {
            return $data;
        }
    }

    /**
     * Get a hash to identify the user.
     *
     * Using the old MD5 here should be fine as we do not use it for cryptography.
     * We need the properties uniformity, efficiency and it must be deterministic.
     * @param int $quizid
     * @param int $userid
     * @return string
     */
    public static function get_user_hash(bool $isteacher, int $quizid, int $userid) {
        if ($isteacher) {
            return md5($userid);
        }
        return md5("{$quizid}_{$userid}");
    }

    /**
     * Event observer when a quiz attempt was started.
     *
     * @param \mod_quiz\event\attempt_started $event
     */
    public static function attempt_started(\mod_quiz\event\attempt_started $event) {
        global $DB;

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!self::watermark_enabled_quiz($attempt->quiz)) {
            return;
        }

        $data = new \stdClass();
        $data->hash = self::get_user_hash(false, $attempt->quiz, $attempt->userid);
        $data->quizid = $attempt->quiz;
        $data->quizattemptid = $attempt->id;
        $data->usageid = $attempt->uniqueid;
        $data->userid = $attempt->userid;
        $data->compact = 0;
        $data->timecreated = $event->timecreated;
        $DB->insert_record('quizaccess_watermark_attempt', $data, false);
    }

    /**
     * Event observer when a quiz attempt was deleted.
     *
     * @param \mod_quiz\event\attempt_deleted $event
     */
    public static function attempt_deleted(\mod_quiz\event\attempt_deleted $event) {
        global $DB;
        $wmattempt = $DB->get_record('quizaccess_watermark_attempt', ['quizattemptid' => $event->objectid]);
        if ($wmattempt) {
            $DB->delete_records('quizaccess_watermark_data', ['usageid' => $wmattempt->usageid]);
            $DB->delete_records('quizaccess_watermark_attempt', ['id' => $wmattempt->id]);
        }
    }
}
