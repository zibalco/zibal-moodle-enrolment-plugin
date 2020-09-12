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
 * zibal enrolments plugin settings and presets.
 * @package    enrol_zibal
 * @copyright  2020 Zibal<zibal.ir>
 * @author     Yahya Kangi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_zibal_settings', '', get_string('pluginname_desc', 'enrol_zibal')));

    $settings->add(new admin_setting_configtext('enrol_zibal/merchant_id',
                   get_string('merchant_id', 'enrol_zibal'),
                   'Copy API Login ID from merchant account & paste here', '', PARAM_RAW));;
    $settings->add(new admin_setting_configcheckbox('enrol_zibal/checkproductionmode',
                   get_string('checkproductionmode', 'enrol_zibal'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_zibal/mailstudents', get_string('mailstudents', 'enrol_zibal'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_zibal/mailteachers', get_string('mailteachers', 'enrol_zibal'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_zibal/mailadmins', get_string('mailadmins', 'enrol_zibal'), '', 0));

    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_zibal/expiredaction', get_string('expiredaction', 'enrol_zibal'), get_string('expiredaction_help', 'enrol_zibal'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_zibal_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_zibal/status',
        get_string('status', 'enrol_zibal'), get_string('status_desc', 'enrol_zibal'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_zibal/cost', get_string('cost', 'enrol_zibal'), '', 0, PARAM_FLOAT, 4));

    $zibalcurrencies = enrol_get_plugin('zibal')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_zibal/currency', get_string('currency', 'enrol_zibal'), '', 'USD', $zibalcurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_zibal/roleid',
            get_string('defaultrole', 'enrol_zibal'), get_string('defaultrole_desc', 'enrol_zibal'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_zibal/enrolperiod',
        get_string('enrolperiod', 'enrol_zibal'), get_string('enrolperiod_desc', 'enrol_zibal'), 0));
}
