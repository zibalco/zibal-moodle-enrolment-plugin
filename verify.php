<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Landing page of Organization Manager View (Approvels)
 *
 * @package    enrol
 * @subpackage zibal
 * @copyright  2020 Zibal<zibal.ir>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once("lib.php");
global $CFG, $_SESSION, $USER, $DB, $OUTPUT;
// require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
$systemcontext = context_system::instance();
$plugininstance = new enrol_zibal_plugin();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/enrol/zibal/verify.php');
echo $OUTPUT->header();
$MerchantID = $plugininstance->get_config('merchant_id');
$testing = $plugininstance->get_config('checkproductionmode');
$Price = $_SESSION['totalcost'];
$trackId = $_GET['trackId'];

$data = new stdClass();
$plugin = enrol_get_plugin('zibal');
$today = date('Y-m-d');
if (isset($_GET['status']) && isset($_GET['trackId'])) {
    if ($_GET['status'] == '2') {
        if ($testing == 0) {
            $MerchantID = "zibal";
        }
    
        $data_array = array(
            'merchant' => $MerchantID,
            'trackId'  => $trackId,
        );
    
        $res = postToZibal("verify", $data_array);

        if ($res->result == 100 && $Price==$res->amount) { 
            $Refnumber = $trackId;
            $Resnumber = $trackId;
            $Status = $res->status;
            $Result = $res->result;
            $PayPrice = $Price;

            $coursename = $DB->get_field('course', 'fullname', ['id' => $_SESSION['courseid']]);
            $data->userid = $_SESSION['userid'];
            $data->courseid = $_SESSION['courseid'];
            $data->instanceid = $_SESSION['instanceid'];
            $coursecost = $DB->get_record('enrol', ['enrol' => 'zibal', 'courseid' => $data->courseid]);
            $time = strtotime($today);
            $paidprice = $coursecost->cost;
            $data->amount = $paidprice;
            $data->refnumber = $Refnumber;
            $data->orderid = $Resnumber;
            $data->payment_status = $Status;
            $data->timeupdated = time();
            $data->item_name = $coursename;
            $data->receiver_email = $USER->email;
            $data->receiver_id = $_SESSION['userid'];

            if (!$user = $DB->get_record("user", ["id" => $data->userid])) {
                message_zibal_error_to_admin("Not a valid user id", $data);
                die;
            }
            if (!$course = $DB->get_record("course", ["id" => $data->courseid])) {
                message_zibal_error_to_admin("Not a valid course id", $data);
                die;
            }
            if (!$context = context_course::instance($course->id, IGNORE_MISSING)) {
                message_zibal_error_to_admin("Not a valid context id", $data);
                die;
            }
            if (!$plugin_instance = $DB->get_record("enrol", ["id" => $data->instanceid, "status" => 0])) {
                message_zibal_error_to_admin("Not a valid instance id", $data);
                die;
            }

            $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

            if ( (float) $plugin_instance->cost <= 0 ) {
                $cost = (float) $plugin->get_config('cost');
            } else {
                $cost = (float) $plugin_instance->cost;
            }

            $cost = format_float($cost, 2, false);

            $data->item_name = $course->fullname;

            $DB->insert_record("enrol_zibal", $data);

            if ($plugin_instance->enrolperiod) {
                $timestart = time();
                $timeend   = $timestart + $plugin_instance->enrolperiod;
            } else {
                $timestart = 0;
                $timeend   = 0;
            }

            $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

            if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                '', '', '', '', false, true)) {
                $users = sort_by_roleassignment_authority($users, $context);
                $teacher = array_shift($users);
            } else {
                $teacher = false;
            }

            $mailstudents = $plugin->get_config('mailstudents');
            $mailteachers = $plugin->get_config('mailteachers');
            $mailadmins   = $plugin->get_config('mailadmins');
            $shortname = format_string($course->shortname, true, array('context' => $context));


            if (!empty($mailstudents)) {
                $a = new stdClass();
                $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

                $eventdata = new \core\message\message();
                $eventdata->courseid          = $course->id;
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_zibal';
                $eventdata->name              = 'zibal_enrolment';
                $eventdata->userfrom          = empty($teacher) ? core_user::get_noreply_user() : $teacher;
                $eventdata->userto            = $user;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);

            }

            if (!empty($mailteachers) && !empty($teacher)) {
                $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->user = fullname($user);

                $eventdata = new \core\message\message();
                $eventdata->courseid          = $course->id;
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_zibal';
                $eventdata->name              = 'zibal_enrolment';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $teacher;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);
            }

            if (!empty($mailadmins)) {
                $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->user = fullname($user);
                $admins = get_admins();
                foreach ($admins as $admin) {
                    $eventdata = new \core\message\message();
                    $eventdata->courseid          = $course->id;
                    $eventdata->modulename        = 'moodle';
                    $eventdata->component         = 'enrol_zibal';
                    $eventdata->name              = 'zibal_enrolment';
                    $eventdata->userfrom          = $user;
                    $eventdata->userto            = $admin;
                    $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = '';
                    $eventdata->smallmessage      = '';
                    message_send($eventdata);
                }
            }
            echo '<h3 style="text-align:center; color: green;">با تشکر از شما، پرداخت شما با موفقیت انجام شد و به  درس انتخاب شده افزوده شدید.</h3>';
            echo '<div class="single_button" style="text-align:center;"><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '"><button>ورود به درس خریداری شده</button></a></div>';
        } else {
            echo '<div style="color:green; font-family:tahoma; direction:rtl; text-align:left">Error in the processing of payment operations , resulting in payment:' . $Status . ' <br /></div>';
        }
    } else {
        echo '<div style="color:red; font-family:tahoma; direction:rtl; text-align:right">
        خطا در انجام تراکنش - خطا: ' . statusCodes($_GET["status"]) . '
        <br /></div>';
    }
}


//----------------------------------------------------- HELPER FUNCTIONS --------------------------------------------------------------------------

/**
 * connects to zibal's rest api
 * @param $path
 * @param $parameters
 * @return stdClass
 */
function postToZibal($path, $parameters)
{
    $url = 'https://gateway.zibal.ir/v1/'.$path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response  = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

/**
 * returns a string message based on result parameter from curl response
 * @param $code
 * @return String
 */
function resultCodes($code)
{
    switch ($code) 
    {
        case 100:
            return "با موفقیت تایید شد";
        
        case 102:
            return "merchant یافت نشد";

        case 103:
            return "merchant غیرفعال";

        case 104:
            return "merchant نامعتبر";

        case 201:
            return "قبلا تایید شده";
        
        case 105:
            return "amount بایستی بزرگتر از 1,000 ریال باشد";

        case 106:
            return "callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)";

        case 113:
            return "amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.";

        case 201:
            return "قبلا تایید شده";
        
        case 202:
            return "سفارش پرداخت نشده یا ناموفق بوده است";

        case 203:
            return "trackId نامعتبر می‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
}

/**
 * returns a string message based on status parameter from $_GET
 * @param $code
 * @return String
 */
function statusCodes($code)
{
    switch ($code) 
    {
        case -1:
            return "در انتظار پردخت";
        
        case -2:
            return "خطای داخلی";

        case 1:
            return "پرداخت شده - تاییدشده";

        case 2:
            return "پرداخت شده - تاییدنشده";

        case 3:
            return "لغوشده توسط کاربر";
        
        case 4:
            return "‌شماره کارت نامعتبر می‌باشد";

        case 5:
            return "‌موجودی حساب کافی نمی‌باشد";

        case 6:
            return "رمز واردشده اشتباه می‌باشد";

        case 7:
            return "‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد";
        
        case 8:
            return "‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 9:
            return "مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 10:
            return "‌صادرکننده‌ی کارت نامعتبر می‌باشد";
        
        case 11:
            return "خطای سوییچ";

        case 12:
            return "کارت قابل دسترسی نمی‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
}


function message_zibal_error_to_admin($subject, $data)
{
    echo $subject;
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new \core\message\message();
    $eventdata->modulename = 'moodle';
    $eventdata->component = 'enrol_zibal';
    $eventdata->name = 'zibal_enrolment';
    $eventdata->userfrom = $admin;
    $eventdata->userto = $admin;
    $eventdata->subject = "zibal ERROR: " . $subject;
    $eventdata->fullmessage = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';
    $eventdata->smallmessage = '';
    message_send($eventdata);
}

echo $OUTPUT->footer();
