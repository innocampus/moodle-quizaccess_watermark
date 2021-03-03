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
 * Global configuration settings for plugin.
 *
 * @package    quizaccess_waterlimit
 * @copyright  2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_configcolourpicker('quizaccess_watermark/background_color',
            get_string('setting:background_color', 'quizaccess_watermark'),
            '',
            '#e7f3f5'));

    $settings->add(new admin_setting_configcolourpicker('quizaccess_watermark/start_color',
            get_string('setting:start_color', 'quizaccess_watermark'),
            '',
            '#e0ebed'));

    $settings->add(new admin_setting_configcolourpicker('quizaccess_watermark/bit_color',
            get_string('setting:bit_color', 'quizaccess_watermark'),
            '',
            '#eefbfd'));
}
