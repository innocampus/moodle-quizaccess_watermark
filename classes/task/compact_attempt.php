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
 * Compact the answers in one quiz attempt to save space.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace quizaccess_watermark\task;

use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use JsonException;
use mod_quiz\quiz_attempt;
use quizaccess_watermark\attempt as watermark_attempt;

defined('MOODLE_INTERNAL') || die();

/**
 * Compact the answers in one quiz attempt to save space.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class compact_attempt extends scheduled_task {

    /**
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('task_compact_attempt', 'quizaccess_watermark');
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function execute(): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal([quiz_attempt::FINISHED, quiz_attempt::ABANDONED], SQL_PARAMS_NAMED);
        $sql = "SELECT wm.*
                  FROM {quizaccess_watermark_attempt} wm
                  JOIN {quiz_attempts} a ON (a.id = wm.quizattemptid)
                 WHERE wm.compact = :mrows
                       AND a.state $insql";
        $params['mrows'] = watermark_attempt::DATA_MULTIPLE_ROWS;
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            mtrace("  Compact watermark attempt id $record->id");
            $attempt = new watermark_attempt($record);
            $attempt->compact_and_save();
        }
    }
}
