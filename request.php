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
 * @package    enrol_zibal
 * @copyright  2020 Zibal<zibal.ir>
 * @author     Yahya Kangi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once("lib.php");
global $CFG, $_SESSION, $USER, $DB;

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$plugininstance = new enrol_zibal_plugin();
if (!empty($_POST['multi'])) {
    $instance_array = unserialize($_POST['instances']);
    $ids_array = unserialize($_POST['ids']);
    $_SESSION['idlist']  =implode(',', $ids_array);
    $_SESSION['inslist']  =implode(',', $instance_array);
    $_SESSION['multi'] = $_POST['multi'];
 } else {
    $_SESSION['courseid'] = $_POST['course_id'];
    $_SESSION['instanceid'] = $_POST['instance_id'];
}
$_SESSION['totalcost']= $_POST['amount'];
$_SESSION['userid'] = $USER->id;
$Price = $_POST['amount'];

$MerchantID = $plugininstance->get_config('merchant_id');
$testing = $plugininstance->get_config('checkproductionmode');
$use_zibal = $plugininstance->get_config('usezibal');
$ReturnPath = $CFG->wwwroot.'/enrol/zibal/verify.php';
$ResNumber = date('YmdHis');// Order Id In Your System
$Description = 'پرداخت شهریه ' . $_POST['item_name'];
$Paymenter = $USER->firstname. ' ' .$USER->lastname;
$Email = $USER->email;
$Mobile = $USER->phone1;

if ($testing == 0) {
    $MerchantID = "zibal";
}

$intPrice = (int) $Price;

$data_array = array (
    "merchant" => $MerchantID,
    "amount" => $intPrice,
    "callbackUrl" => $ReturnPath,
    "Description" => $Description,
    "mobile" => $Mobile,
);

$res = PostToZibal("request", $data_array);

if ($res->result == 100) {
    Header('Location: https://gateway.zibal.ir/start/'.$res->trackId);
} else {
    echo'ERR: '.$res->result. ' '.resultCodes($res->result);
}

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
            return "";
    }
}