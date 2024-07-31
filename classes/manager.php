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

use coding_exception;
use dml_exception;
use mod_quiz\event\attempt_deleted;
use mod_quiz\event\attempt_started;
use stdClass;

defined('MOODLE_INTERNAL') || die();

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
    private static bool $savedpostdata = false;

    /**
     * @var null|bool Watermark is enabled for the quiz on this page.
     */
    private static ?bool $watermarkenabled = null;

    /**
     * Check if watermark is enabled for the quiz.
     *
     * @param $usageid
     * @return bool
     * @throws dml_exception
     */
    private static function watermark_enabled($usageid): bool {
        if (self::$watermarkenabled === null) {
            global $DB;
            self::$watermarkenabled = $DB->record_exists('quizaccess_watermark_attempt', ['usageid' => $usageid]);
        }
        return self::$watermarkenabled;
    }

    /**
     * @throws dml_exception
     */
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
     * @throws dml_exception
     */
    public static function save_post_data(int $usageid, $postdata): void {
        if (!self::$savedpostdata) {
            self::$savedpostdata = true;
            if (!self::watermark_enabled($usageid)) {
                return;
            }

            // Only quiz autosave and processattempt.
            if (!str_ends_with($_SERVER['SCRIPT_FILENAME'], 'autosave.ajax.php') &&
                !str_ends_with($_SERVER['SCRIPT_FILENAME'], 'processattempt.php')) {
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
            $record = new stdClass();
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
     * @throws dml_exception
     */
    public static function clean_answer_data(int $usageid, array $data): array {
        // Check POST because self::watermark_enabled returns false for preview attempts.
        if (isset($_POST['quizaccess_watermark_enable_clean']) || self::watermark_enabled($usageid)) {
            $new = [];
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $replaced = preg_replace('/[\x{2060}-\x{2064}\x{E0060}-\x{E007F}]/u', '', $value);
                } else {
                    $replaced = $value;
                }
                $new[$key] = ($replaced !== null) ? $replaced : $value; // Keep value as it is when an error occurs.
            }
            return $new;
        } else {
            return $data;
        }
    }

    /**
     * Get a string with hexadecimal digits to identify the user.
     *
     * @param bool $isteacher
     * @param int $quizid
     * @param int $userid
     * @param bool $forcenew
     * @return string
     * @throws dml_exception
     */
    public static function get_user_hash(bool $isteacher, int $quizid, int $userid, bool $forcenew = false): string {
        global $SESSION, $DB;
        if ($isteacher) {
            return md5($userid);
        }
        if (!$forcenew) {
            // Try to avoid a db query.
            if (isset($SESSION->quizaccess_watermark[$quizid])) {
                return $SESSION->quizaccess_watermark[$quizid];
            }
            $sql = "SELECT hash
                      FROM {quizaccess_watermark_attempt}
                     WHERE userid = :user AND quizid = :quiz
                  ORDER BY timecreated DESC";
            $records = $DB->get_records_sql($sql, ['user' => $userid, 'quiz' => $quizid], 0, 1);
            if (count($records)) {
                $hash = current($records);
                $SESSION->quizaccess_watermark[$quizid] = $hash;
                return $hash;
            }
        }

        // Generate 64 bit unsigned int.
        $rand = str_pad(dechex(rand(0, 0xffff_ffff)), 8, '0', STR_PAD_LEFT);
        $rand .= str_pad(dechex(rand(0, 0xffff_ffff)), 8, '0', STR_PAD_LEFT);
        $SESSION->quizaccess_watermark[$quizid] = $rand;
        return $rand;
    }

    /**
     * Event observer when a quiz attempt was started.
     *
     * @param attempt_started $event
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function attempt_started(attempt_started $event): void {
        global $DB;

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!self::watermark_enabled_quiz($attempt->quiz)) {
            return;
        }

        $data = new stdClass();
        $data->hash = self::get_user_hash(false, $attempt->quiz, $attempt->userid, true);
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
     * @param attempt_deleted $event
     * @throws dml_exception
     */
    public static function attempt_deleted(attempt_deleted $event): void {
        global $DB;
        $wmattempt = $DB->get_record('quizaccess_watermark_attempt', ['quizattemptid' => $event->objectid]);
        if ($wmattempt) {
            $DB->delete_records('quizaccess_watermark_data', ['usageid' => $wmattempt->usageid]);
            $DB->delete_records('quizaccess_watermark_attempt', ['id' => $wmattempt->id]);
        }
    }
}
