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
 * Implementation of the quizaccess_watermark plugin.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the quizaccess_watermark plugin.
 *
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_watermark extends access_rule_base {

    /**
     * @inheritDoc
     * @return self|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits): ?self {
        if ($quizobj->get_quiz()->watermark_enabled ?? false) {
            return new self($quizobj, $timenow);
        }
        return null;
    }

    /**
     * @inheritDoc
     * @throws dml_exception
     */
    public function setup_attempt_page($page): void {
        global $USER;

        $settings = get_config('quizaccess_watermark');

        $teacher = $this->quizobj->has_capability('mod/quiz:viewreports');
        $userhash = quizaccess_watermark\manager::get_user_hash($teacher, $this->quizobj->get_quizid(), $USER->id);
        $page->requires->js_call_amd(
            'quizaccess_watermark/watermark-lazy',
            'init',
            [$teacher, $userhash, $settings->background_color, $settings->start_color, $settings->bit_color],
        );
    }

    /**
     * @inheritDoc
     */
    public function is_preflight_check_required($attemptid): bool {
        // Warning only required if the attempt is not already started.
        return $attemptid === null;
    }

    /**
     * @inheritDoc
     * @throws coding_exception
     */
    public function add_preflight_check_form_fields(preflight_check_form $quizform, MoodleQuickForm $mform, $attemptid): void {
        $mform->addElement('header', 'watermarkheader', get_string('settings_header', 'quizaccess_watermark'));
        $mform->addElement('static', 'watermarkmessage', '', get_string('preflight_text', 'quizaccess_watermark'));
    }

    /**
     * @inheritDoc
     * @throws coding_exception
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform): void {
        if (!has_capability('quizaccess/watermark:manage_quiz_settings', $quizform->get_context())) {
            return;
        }
        $header = $mform->createElement('header', 'watermark', get_string('settings_header', 'quizaccess_watermark'));
        $mform->insertElementBefore($header, 'security');

        $option = $mform->createElement(
            'selectyesno',
            'watermark_enabled',
            get_string('settings_watermark_enable', 'quizaccess_watermark'),
        );
        $mform->setDefault('watermark_enabled', 0);
        $mform->insertElementBefore($option, 'security');
    }

    /**
     * @inheritDoc
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function save_settings($quiz): void {
        global $DB;

        $context = context_module::instance($quiz->coursemodule);
        if (!has_capability('quizaccess/watermark:manage_quiz_settings', $context)) {
            return;
        }

        $record = new stdClass();
        $record->quizid = $quiz->id;
        $record->watermark = $quiz->watermark_enabled ?? false;

        if (!$record->watermark) {
            $DB->delete_records('quizaccess_watermark', ['quizid' => $quiz->id]);
            return;
        }

        $existing = $DB->get_record('quizaccess_watermark', ['quizid' => $quiz->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_watermark', $record);
        } else {
            $DB->insert_record('quizaccess_watermark', $record);
        }
    }

    /**
     * @inheritDoc
     * @throws dml_exception
     */
    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_watermark', ['quizid' => $quiz->id]);
    }

    /**
     * @inheritDoc
     */
    public static function get_settings_sql($quizid): array {
        return [
            'watermark.watermark AS watermark_enabled',
            'LEFT JOIN {quizaccess_watermark} watermark ON watermark.quizid = quiz.id',
            []
        ];
    }
}
