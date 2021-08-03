<h1>Paytm Payment Api Using Laravel 8</h1>
    <h3>Introduction</h3>
    <p>
      Integrate paytm wallet in your application using paytm payment Api in
      laravel.In this project we use official Paytm PHP SDK's.
    </p>
    <h3>Getting Started</h3>
    <p>To get started, Install this package in your laravel project</p>
    <pre>
    <code>composer require paytm/paytmchecksum</code>
    </pre>
    <p
      style="padding: 10px 10px 10px 10px; background-color: rgb(212, 208, 208)"
    >
      <span><b>Step :- 1 -</b></span> First you need to create merchant account
      on paytm.Or If you have an account then login.
    </p>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span
      >Click Here go to paytm developer Section - </span
    ><a href="https://developer.paytm.com/">Paytm Developer Account</a>
    <p>
      <span
        ><b>Step :- 2 - </b>Now go to developer settings and go to api keys
        section and get test api detail. And put these detail on .env file</span
      >
    </p>
    <h6>Now Add the paytm credentials in your <code>.env</code>file</h6>
    <code>
      PAYTM_ENVIRONMENT=local 
      PAYTM_MERCHANT_ID=YOUR_MERCHANT_ID_HERE
      PAYTM_MERCHANT_KEY=YOUR_SECRET_KEY_HERE
      PAYTM_MERCHANT_WEBSITE=YOUR_MERCHANT_WEBSITE
      PAYTM_CHANNEL=YOUR_CHANNEL_HERE
      PAYTM_INDUSTRY_TYPE=YOUR_INDUSTRY_TYPE_HERE
    </code>
    <p>Now Add paytm's utility package in your app. Go to project and create file in <code>App/paytm</code> folder and name that file <code>PaytmChecksum.php</code></p>
    <p>here is the link to download <code>PaytmChecksum.php</code> file.on folder <code>App/paytm/</code>  <a href="https://github.com/paytm/Paytm_PHP_Checksum/blob/master/paytmchecksum/PaytmChecksum.php">paytm package</a></p>
    <p>Now Create A controller <code>php artisan make:controller PaymentController</code></p>
    <h2>Usage</h2>
    <h5>Making Transaction via Api</h5>
    <code>
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
                            "callbackUrl" => url('/api/check-transaction-status/'.$orderid),
                            "txnAmount" => array(
                                "value" => $request->amount,
                                "currency" => "INR",
                            ),
                            "userInfo" => array(
                                "custId" => auth()->user()->id,
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
                                'callbackurl' => url('/api/' . $version . '/check-transaction-status'),
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
}

    </code>
