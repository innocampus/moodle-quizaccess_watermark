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
 * Strings for plugin.
 *
 * @package    quizaccess_watermark
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Watermark';

$string['watermark:manage_quiz_settings'] = 'Manage watermark quiz settings';
$string['watermark:view_reports'] = 'View watermark reports';

$string['settings_header'] = 'Fraud detection measures';
$string['settings_watermark_enable'] = 'Add watermarks to detect copied answers (experimental)';
$string['task_compact_attempt'] = 'Compact attempt data for watermark detection';

$string['preflight_text'] = 'The exam is protected by security measures, which reveals your identity if you share answers or screenshots!';

$string['report'] = 'Report';
$string['report_answer'] = 'Answer';
$string['report_answer_time'] = 'Time';
$string['report_attempt'] = 'Attempt';
$string['report_watermark_found'] = 'Foreign watermark hashes found';
$string['report_watermark_found_from'] = 'Watermark found from';
$string['report_user_watermark'] = 'Watermark/hash of this user: {$a}';
$string['watermark_unknown'] = 'Unknown watermark';

$string['setting:background_color'] = 'Background color of questions';
$string['setting:start_color'] = 'Color to find the start a of the watermark';
$string['setting:bit_color'] = 'Color that denotes bit 1';
