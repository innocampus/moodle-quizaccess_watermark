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
 * Watermark detection in texts within one attempt.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_watermark;

defined('MOODLE_INTERNAL') || die();

/**
 * Watermark detection in texts within one attempt.
 *
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt {
    /** @var \stdClass */
    private $wmattempt;

    const DATA_MULTIPLE_ROWS = 0;
    const DATA_COMPACT = 1;

    const MIMINUM_HEX_CHARS = 4;

    /** @var array saved json data (sorted by time) */
    private $data = [];

    private $ownhash = '';

    /**
     * attempt constructor.
     *
     * @param \stdClass $wmattempt one db record of table quizaccess_watermark_attempt
     */
    public function __construct(\stdClass $wmattempt) {
        global $DB;

        $this->wmattempt = $wmattempt;
        $this->ownhash = $this->wmattempt->hash;

        $records = $DB->get_records('quizaccess_watermark_data', ['usageid' => $wmattempt->usageid]);
        if ($wmattempt->compact == 1 && count($records) > 0) {
            // There should be only one row.
            $uncompressed = gzuncompress(current($records)->json);
            if ($uncompressed === false) {
                throw new \moodle_exception('gzuncompress_error', 'quizaccess_watermark');
            }
            $this->data = json_decode($uncompressed, false, 4, JSON_INVALID_UTF8_IGNORE|JSON_THROW_ON_ERROR);

        } else {
            foreach ($records as $record) {
                $this->data[] = json_decode($record->json, false, 4, JSON_INVALID_UTF8_IGNORE|JSON_THROW_ON_ERROR);
            }
            usort($this->data, function($a, $b) {
                return $a->time - $b->time;
            });
        }
    }

    /**
     * Get watermark attempt from quiz attempt id.
     *
     * @param int $attemptid
     * @return attempt
     */
    public static function get_from_quiz_attempt_id(int $attemptid) {
        global $DB;

        $attempt = $DB->get_record('quizaccess_watermark_attempt', ['quizattemptid' => $attemptid]);
        if ($attempt === false) {
            throw new \moodle_exception('attempt_not_found', 'quizaccess_watermark');
        }
        return new self($attempt);
    }

    public function get_quiz_id() : int {
        return $this->wmattempt->quizid;
    }

    public function get_hash() : string {
        return $this->ownhash;
    }

    public function get_user_fullname() : string {
        return fullname(\core_user::get_user($this->wmattempt->userid));
    }

    /**
     * Bundle and compress attempt data.
     *
     * Deletes the old rows and inserts one row with all the data as a gz-compressed json.
     *
     */
    public function compact_and_save() {
        global $DB;

        $newdata = json_encode($this->data, JSON_INVALID_UTF8_IGNORE|JSON_THROW_ON_ERROR, 4);
        $newdata = gzcompress($newdata, 9);
        if ($newdata === false) {
            throw new \moodle_exception('gzcompress_error', 'quizaccess_watermark');
        }
        $record = new \stdClass();
        $record->usageid = $this->wmattempt->usageid;
        $record->json = $newdata;

        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('quizaccess_watermark_attempt', 'compact', self::DATA_COMPACT, ['id' => $this->wmattempt->id]);
        $DB->delete_records('quizaccess_watermark_data', ['usageid' => $this->wmattempt->usageid]);
        $DB->insert_record('quizaccess_watermark_data', $record, false);
        $transaction->allow_commit();
        $this->wmattempt->compact = 1;
    }

    /**
     * Find all watermarks in all answers that do not belong to the user.
     *
     * @return array
     */
    public function find_foreign_watermarks() : array {
        $found = [];
        foreach ($this->data as $step) {
            foreach ($step->data as $key => $answer) {
                if (is_string($answer)) {
                    foreach (self::find_watermarks_in_string($answer) as $watermark) {
                        if (substr($this->ownhash, 0, strlen($watermark)) != $watermark) {
                            $hit = self::find_attempt_hash($watermark, $this->get_quiz_id());
                            $found[] = [
                                    'date' => $step->time,
                                    'answer' => $answer,
                                    'watermark' => $watermark,
                                    'hit' => $hit,
                            ];
                        }
                    }
                }
            }
        }

        return $found;
    }

    private static function find_attempt_hash(string $hash, int $quizid) {
        global $DB;

        $userfields = get_all_user_name_fields(true, 'u');
        $hash = $DB->sql_like_escape($hash);
        $records = $DB->get_records_sql("
            SELECT wa.*, $userfields
            FROM {quizaccess_watermark_attempt} wa
            LEFT JOIN {user} u ON (u.id = wa.userid)
            WHERE wa.hash LIKE '{$hash}%' AND wa.quizid = :quiz
        ", ['quiz' => $quizid]);

        if ($records === false || count($records) != 1) {
            return null;
        }

        $record = current($records);
        return [
            'userid' => $record->userid,
            'quizattemptid' => $record->quizattemptid,
            'fullname' => fullname($record),
        ];
    }

    /**
     * Find and extract watermarks in a string.
     *
     * @param string $str
     * @return array
     */
    private static function find_watermarks_in_string(string $str) : array {
        $watermarks = [];
        $matches = [];
        $ok = preg_match_all('/[\x{2060}-\x{2063}]{1,16}/u', $str, $matches, PREG_PATTERN_ORDER);
        if ($ok === false) {
            throw new \moodle_exception('find_watermark_regex_error1', 'quizaccess_watermark');
        }
        foreach($matches[0] as $match) {
            $hex = self::get_hex($match, 0x2060);
            if (strlen($hex) >= self::MIMINUM_HEX_CHARS) {
                $watermarks[] = $hex;
            }
        }

        $matches = [];
        $ok = preg_match_all('/[\x{E0061}-\x{E0070}]{1,16}/u', $str, $matches, PREG_PATTERN_ORDER);
        if ($ok === false) {
            throw new \moodle_exception('find_watermark_regex_error2', 'quizaccess_watermark');
        }
        foreach($matches[0] as $match) {
            $hex = self::get_hex($match, 0xE0061);
            if (strlen($hex) >= self::MIMINUM_HEX_CHARS) {
                $watermarks[] = $hex;
            }
        }

        return array_unique($watermarks);
    }

    /**
     * Get hexadecimal string representation of a watermark.
     *
     * @param string $str
     * @param int $base
     * @return string
     */
    private static function get_hex(string $str, int $base) : string {
        $hexstring = '';
        $ordbefore = 0;
        foreach (mb_str_split($str) as $char) {
            $ord = mb_ord($char);
            if ($base == 0x2060) {
                if ($ordbefore != 0) {
                    $no = (($ordbefore - $base) << 2) | ($ord - $base);
                    $hexstring .= dechex($no);
                    $ordbefore = 0;
                } else {
                    $ordbefore = $ord;
                }
            } else if ($base == 0xE0061) {
                $hexstring .= dechex($ord - $base);
            }
        }

        return $hexstring;
    }

    public static function find_all_users_with_foreign_watermarks(int $quizid) {
        global $DB;

        $found = [];
        $attempts = $DB->get_records('quizaccess_watermark_attempt', ['quizid' => $quizid]);
        foreach ($attempts as $attempt) {
            $attemptobj = new self($attempt);
            $hits = $attemptobj->find_foreign_watermarks();
            if ($hits) {
                $found[] = [
                    'quizattemptid' => $attempt->quizattemptid,
                    'userfullname' => $attemptobj->get_user_fullname(),
                    'watermarksfrom' => self::get_unique_found_users($hits),
                ];
            }
        }

        return $found;
    }

    private static function get_unique_found_users(array $hits) {
        $users = [];
        foreach ($hits as $hit) {
            if ($hit['hit']) {
                $id = $hit['hit']['userid'];
                $users[$id] = $hit['hit']['fullname'];
            } else {
                $users[0] = get_string('watermark_unknown', 'quizaccess_watermark');
            }
        }
        return implode(', ', $users);
    }
}
