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

$string['pluginname'] = 'Wasserzeichen/Betrugserkennung';

$string['watermark:manage_quiz_settings'] = 'Wasserzeichen-Einstellungen verwalten';
$string['watermark:view_reports'] = 'Wasserzeichen-Bericht sehen';

$string['settings_header'] = 'Maßnahmen zur Betrugserkennung';
$string['settings_watermark_enable'] = 'Wasserzeichen hinzufügen, um kopierte Antworten erkennen zu können (experimentell)';
$string['task_compact_attempt'] = 'Komprimiere die Daten der Wasserzeichen-Erkennung';

$string['preflight_text'] = 'Dieser Test wird durch Sicherheitsmaßnahmen geschützt, mit denen Sie identifiziert werden können, wenn Sie Antworten oder Screenshots mit anderen teilen!';

$string['report'] = 'Bericht';
$string['report_answer'] = 'Antwort';
$string['report_answer_time'] = 'Zeit';
$string['report_attempt'] = 'Versuch';
$string['report_watermark_found'] = 'Fremde Wasserzeichen/Hashes gefunden';
$string['report_watermark_found_from'] = 'Wasserzeichen gefunden von';
$string['report_user_watermark'] = 'Wasserzeichen/Hash des Users: {$a}';
$string['watermark_unknown'] = 'Unbekanntes Wasserzeichen';

$string['setting:background_color'] = 'Hintergrundfarbe von Fragen';
$string['setting:start_color'] = 'Farbe zum Auffinden des Beginns des Wasserzeichens';
$string['setting:bit_color'] = 'Farbe, die das Bit 1 zeigt';
