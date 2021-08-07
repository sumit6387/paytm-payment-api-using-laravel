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
    <h4> <b>Step :- 3 - </b>Now Add the paytm credentials in your <code>.env</code>file</h4>
    <pre>
    <code>
      PAYTM_ENVIRONMENT=local 
      PAYTM_MERCHANT_ID=YOUR_MERCHANT_ID_HERE
      PAYTM_MERCHANT_KEY=YOUR_SECRET_KEY_HERE
      PAYTM_MERCHANT_WEBSITE=YOUR_MERCHANT_WEBSITE
      PAYTM_CHANNEL=YOUR_CHANNEL_HERE
      PAYTM_INDUSTRY_TYPE=YOUR_INDUSTRY_TYPE_HERE
    </code>
  </pre>
    <p> <b>Step :- 4 - </b>Now Add paytm's utility package in your app. Go to project and create file in <code>App/paytm/paytmchecksum</code> folder and name that file <code>PaytmChecksum.php</code></p>
    <p>here is the link to download <code>PaytmChecksum.php</code> file.on folder <code>App/paytm/paytmchecksum</code>  <a href="https://github.com/paytm/Paytm_PHP_Checksum/blob/master/paytmchecksum/PaytmChecksum.php">paytm package</a></p>
    <p>Now Create A controller <code>php artisan make:controller PaymentController</code></p>
    <h2>Usage</h2>
    <h5> <b>Step :- 5 - </b>Making Transaction via Api</h5>
    <pre>
    <code>
      <p><?php</p>

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
                                    //this is the testing/staging url
                                $url = "https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction?mid=" . $mid . "&orderId=" . $orderid;
                            } else {
                            //this is the production url
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
                                    'callbackurl' => url('/api/' . $version . '/check-transaction-status/'.$orderid),
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

  </pre>
  <p><b>Step :- 6 - </b>After creating payment request now verify the status of transaction using <code>orderid</code> .</p>
  <pre>
    <code>
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
              'msg' => $msg
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => $msg,
            ]);
        }
    }
  </pre>

  <p><b>Step :- 7 - </b>Now Add the code on <code>Routes/api.php</code></p>
  <pre>
    <code>
      Route::post('/initiate-payment', [PaymentController::class, 'paymentInitiate']); //amount   there you can pass only amount
      Route::get('/check-transaction-status/{orderid}', [PaymentController::class, 'checkTransactionStatus']); //orderid
  </pre>
  
  <h2> Step :- 8 - Now Run Your Project And try these route on postman.</h2>
