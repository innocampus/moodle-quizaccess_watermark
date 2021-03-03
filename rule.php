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

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the quizaccess_watermark plugin.
 *
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_watermark extends quiz_access_rule_base {

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     * @param quiz $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from
     *      time limits by the mod/quiz:ignoretimelimits capability.
     * @return quiz_access_rule_base|null the rule, if applicable, else null.
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if ($quizobj->get_quiz()->watermark_enabled) {
            return new self($quizobj, $timenow);
        }
        return null;
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule. securewindow rule is an example of where
     * this is used.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        global $USER;

        $settings = get_config('quizaccess_watermark');

        $teacher = $this->quizobj->has_capability('mod/quiz:viewreports');
        $userhash = quizaccess_watermark\manager::get_user_hash($teacher, $this->quizobj->get_quizid(), $USER->id);
        $page->requires->js_call_amd('quizaccess_watermark/watermark-lazy', 'init', [
            $teacher, $userhash, $settings->background_color, $settings->start_color, $settings->bit_color
        ]);
    }

    public function is_preflight_check_required($attemptid) {
        // Warning only required if the attempt is not already started.
        return $attemptid === null;
    }

    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {

        $mform->addElement('header', 'watermarkheader',
                get_string('settings_header', 'quizaccess_watermark'));
        $mform->addElement('static', 'watermarkmessage', '',
                get_string('preflight_text', 'quizaccess_watermark'));
    }

    /**
     * Add any fields that this rule requires to the quiz settings form. This
     * method is called from {@link mod_quiz_mod_form::definition()}, while the
     * security seciton is being built.
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {

        if (has_capability('quizaccess/watermark:manage_quiz_settings', $quizform->get_context())) {
            $header = $mform->createElement('header', 'watermark',
                    get_string('settings_header', 'quizaccess_watermark'));
            $mform->insertElementBefore($header, 'security');

            $option = $mform->createElement('selectyesno', 'watermark_enabled',
                    get_string('settings_watermark_enable', 'quizaccess_watermark'));
            $mform->setDefault('watermark_enabled', 0);
            $mform->insertElementBefore($option, 'security');
        }
    }

    /**
     * Save any submitted settings when the quiz settings form is submitted. This
     * is called from {@link quiz_after_add_or_update()} in lib.php.
     * @param object $quiz the data from the quiz form, including $quiz->id
     *      which is the id of the quiz being saved.
     */
    public static function save_settings($quiz) {
        global $DB;

        $context = context_module::instance($quiz->coursemodule);
        if (!has_capability('quizaccess/watermark:manage_quiz_settings', $context)) {
            return;
        }

        $record = new stdClass();
        $record->quizid = $quiz->id;
        $record->watermark = $quiz->watermark_enabled;

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
     * Delete any rule-specific settings when the quiz is deleted. This is called
     * from {@link quiz_delete_instance()} in lib.php.
     * @param object $quiz the data from the database, including $quiz->id
     *      which is the id of the quiz being deleted.
     * @since Moodle 2.7.1, 2.6.4, 2.5.7
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_watermark', ['quizid' => $quiz->id]);
    }

    /**
     * Return the bits of SQL needed to load all the settings from all the access
     * plugins in one DB query. The easiest way to understand what you need to do
     * here is probalby to read the code of {@link quiz_access_manager::load_settings()}.
     *
     * If you have some settings that cannot be loaded in this way, then you can
     * use the {@link get_extra_settings()} method instead, but that has
     * performance implications.
     *
     * @param int $quizid the id of the quiz we are loading settings for. This
     *     can also be accessed as quiz.id in the SQL. (quiz is a table alisas for {quiz}.)
     * @return array with three elements:
     *     1. fields: any fields to add to the select list. These should be alised
     *        if neccessary so that the field name starts the name of the plugin.
     *     2. joins: any joins (should probably be LEFT JOINS) with other tables that
     *        are needed.
     *     3. params: array of placeholder values that are needed by the SQL. You must
     *        used named placeholders, and the placeholder names should start with the
     *        plugin name, to avoid collisions.
     */
    public static function get_settings_sql($quizid) {
        return [
            'watermark.watermark AS watermark_enabled',
            'LEFT JOIN {quizaccess_watermark} watermark ON watermark.quizid = quiz.id',
            []
        ];
    }
}
