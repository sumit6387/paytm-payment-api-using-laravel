<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use paytm\paytmchecksum\PaytmChecksum;
use Validator;

class PaymentController extends Controller
{
    public function paymentInitiate(Request $request)
    {
        date_default_timezone_set("Asia/Kolkata");
        $valid = Validator::make($request->all(), [
            'amount' => "required",
        ]);

        if ($valid->passes()) {
            $paytmParams = array();
            $orderid = Str::random(12) . rand(11111111, 99999999);
            $mid = env('PAYTM_MERCHANT_ID');
            // return $mid;
            $paytmParams["body"] = array(
                "requestType" => "Payment",
                "mid" => $mid,
                "websiteName" => env('PAYTM_MERCHANT_WEBSITE'),
                "orderId" => $orderid,
                "callbackUrl" => url('/api/check-transaction-status/' . $orderid),
                "txnAmount" => array(
                    "value" => $request->amount,
                    "currency" => "INR",
                ),
                "userInfo" => array(
                    "custId" => 2,
                ),
            );

            /*
             * Generate checksum by parameters we have in body
             * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys
             */
            $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), env('PAYTM_MERCHANT_KEY'));

            $paytmParams["head"] = array(
                "signature" => $checksum,
            );

            $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
            /* for Staging */

            if (env('PAYTM_ENVIRONMENT') == 'local') {
                $url = "https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction?mid=" . $mid . "&orderId=" . $orderid;
            } else {
                $url = "https://securegw.paytm.in/theia/api/v1/initiateTransaction?mid=" . $mid . "&orderId=" . $orderid;
            }
            /* for Production */
            // $url = "https://securegw.paytm.in/theia/api/v1/initiateTransaction?mid=YOUR_MID_HERE&orderId=ORDERID_98765";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
            $response = curl_exec($ch);
            $status = (json_decode($response)->body->resultInfo->resultStatus);
            $msg = (json_decode($response)->body->resultInfo->resultMsg);
            $txntoken = (json_decode($response)->body->txnToken);
            if ($status == "S" && $msg == "Success") {
                return response()->json([
                    'status' => true,
                    'orderid' => $orderid,
                    'mid' => $mid,
                    'amount' => $request->amount,
                    'txnToken' => $txntoken,
                    'callbackurl' => url('/api/' . $version . '/check-transaction-status/' . $orderid),
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => $msg,
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'msg' => $valid->errors()->all(),
            ]);
        }
    }

    public function checkTransactionStatus($orderid)
    {
        $paytmParams = array();
        /* body parameters */
        $mid = env('PAYTM_MERCHANT_ID');
        $paytmParams["body"] = array(
            /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
            "mid" => $mid,

            /* Enter your order id which needs to be check status for */
            "orderId" => $orderid,
        );

        /**
         * Generate checksum by parameters we have in body
         * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys
         */
        $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), env('PAYTM_MERCHANT_KEY'));

        /* head parameters */
        $paytmParams["head"] = array(
            "signature" => $checksum,
        );

        /* prepare JSON string for request */
        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Staging */

        if (env('PAYTM_ENVIRONMENT') == 'local') {
            $url = "https://securegw-stage.paytm.in/v3/order/status";
        } else {
            $url = "https://securegw.paytm.in/v3/order/status";
        }
        /* for Production */
        // $url = "https://securegw.paytm.in/v3/order/status";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $status = (json_decode($response)->body->resultInfo->resultStatus);
        $msg = (json_decode($response)->body->resultInfo->resultMsg);
        if ($status === "TXN_SUCCESS") {
            $txnid = (json_decode($response)->body->txnId);
            $orderid = (json_decode($response)->body->orderId);
            $amount = (json_decode($response)->body->txnAmount);
            return response()->json([
                "status" => true,
                'msg' => $msg,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => $msg,
            ]);
        }
    }
}
