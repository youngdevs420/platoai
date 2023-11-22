<?php

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Currency;
use App\Models\CustomSettings;
use App\Models\GatewayProducts;
use App\Models\Gateways;
use App\Models\OldGatewayProducts;
use App\Models\PaymentPlans;
use App\Models\Setting;
use App\Models\Subscriptions as SubscriptionsModel;
use App\Models\SubscriptionItems;
use App\Models\HowitWorks;
use App\Models\User;
use App\Models\UserAffiliate;
use App\Models\UserOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Brick\Math\BigDecimal;
use App\Models\Coupon;
use App\Http\Controllers\Gateways\IyzicoActions;
use App\Events\IyzicoWebhookEvent;




/**
 * Controls ALL Payment actions of Iyzico
 */
class IyzicoController extends Controller
{

    // iyzipay actions class
    private $iyzipayActions;

    // constructor
    public function __construct()
    {
        // $settings = $this->retrieveGatewaySettings();
        // $this->iyzipayActions = new IyzicoActions($settings->apiKey, $settings->apiSecretKey, $settings->baseUrl, $settings->currency);
    }

    private function retrieveGatewaySettings()
    {
        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 
        $currency = Currency::where('id', $gateway->currency)->first()->code;
        $settings = Setting::first();

        return [
            'apiKey' => $gateway->mode == 'live' ? $gateway->live_client_id : $gateway->sandbox_client_id,
            'apiSecretKey' => $gateway->mode == 'live' ? $gateway->live_client_secret : $gateway->sandbox_client_secret,
            'baseUrl' =>  $gateway->mode == 'live' ? $gateway->base_url : $gateway->sandbox_url,
            'currency' => $currency,
        ];
    }

    public static function getIyzipayActions(){
        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 
        $currency = Currency::where('id', $gateway->currency)->first()->code;
        $settings = [
            'apiKey' => $gateway->mode == 'live' ? $gateway->live_client_id : $gateway->sandbox_client_id,
            'apiSecretKey' => $gateway->mode == 'live' ? $gateway->live_client_secret : $gateway->sandbox_client_secret,
            'baseUrl' =>  $gateway->mode == 'live' ? $gateway->base_url : $gateway->sandbox_url,
            'currency' => $currency,
        ];
        return new iyzipayActions($settings['apiKey'], $settings['apiSecretKey'], $settings['baseUrl'], \Iyzipay\Model\Locale::TR ,$settings['currency']);
    }


    /**
     * Reads GatewayProducts table and returns price id of the given plan
     */
    public static function getIyzicoPriceId($planId)
    {

        //check if plan exists
        $plan = PaymentPlans::where('id', $planId)->first();
        if ($plan != null) {
            $product = GatewayProducts::where(["plan_id" => $planId, "gateway_code" => "iyzico"])->first();
            if ($product != null) {
                return $product->price_id;
            } else {
                return null;
            }
        }
        return null;
    }


    /**
     * Saves Membership plan product in iyzico gateway.
     * @param planId ID of plan in PaymentPlans model.
     * @param productName Name of the product, plain text
     * @param price Price of product
     * @param frequency Time interval of subscription, month / annual
     * @param type Type of product subscription/one-time
     */
    public static function saveProduct($planId, $productName, $price, $frequency, $type){

        try{

            $iyzipayActions = self::getIyzipayActions();
            

            $plan = PaymentPlans::where('id', $planId)->first();

            $product = null;
            $oldProductId = null;

            //////// PRODUCT ////////

            //check if product exists
            $productData = GatewayProducts::where(["plan_id" => $planId, "gateway_code" => "iyzico"])->first();
            if($productData != null){

                // One-Time price
                if($type == "o"){
                    // iyzico handles one time prices with payments, so we do not need to set anything for one-time payments.
                    $productData->product_id = __('Not Needed');
                    $productData->save();
                }else{

                    // check if product exists in iyzico
                    $prd = json_decode(json_encode([
                        "productReferenceCode" => $productData->product_id,
                    ]));

                    $checkProduct = $iyzipayActions->retrieveSubscriptionProduct($prd);

                    if($checkProduct->getReferenceCode() != null){
                        //Product exists
                        //Log::info("iyzico product exists");
                    }else{
                        //Product does not exist
                        //Log::info("iyzico product does not exist");

                        if($productData->product_id != null){
                            //Product has been created before
                            $oldProductId = $productData->product_id;
                        }else{
                            //Product has NOT been created before but record exists. Create new product and update record.
                        }

                        $prd = json_decode(json_encode([
                            "name"          => $productName,
                        ]));

                        $newProduct = $iyzipayActions->createSubscriptionProduct($prd);

                        //Log::info("iyzico subscription product refreshed");
                        //Log::info("Product : " . json_encode($newProduct));

                        if($newProduct->getReferenceCode() != null){
                            $productData->product_id = $newProduct->getReferenceCode();
                            $productData->plan_name = $productName;
                            $productData->save();
                        }else{
                            Log::error("IyzicoController::saveProduct() - Product could not be created. Product : " . json_encode($newProduct));
                            //return back()->with(['message' => __('(iyzico) Product could not be created. Product : ') . $productName, 'type' => 'error']);
                        }


                    }

                    
            
                }

                $product = $productData;
            }else{
                // One-Time price
                if($type == "o"){
                    // iyzico handles one time prices with payments, so we do not need to set anything for one-time payments.
                    $product = new GatewayProducts();
                    $product->plan_id = $planId;
                    $product->plan_name = $productName;
                    $product->gateway_code = "iyzico";
                    $product->gateway_title = "iyzico";
                    $product->product_id = __('Not Needed');
                    $product->save();
                }else{
                    $prd = json_decode(json_encode([
                        "name"          => $productName,
                    ]));
                    $newProduct = $iyzipayActions->createSubscriptionProduct($prd);
                    
                    if($newProduct->getReferenceCode() != null){
                        //Log::info("iyzico product created");

                        $product = new GatewayProducts();
                        $product->plan_id = $planId;
                        $product->plan_name = $productName;
                        $product->gateway_code = "iyzico";
                        $product->gateway_title = "iyzico";
                        $product->product_id = $newProduct->getReferenceCode();
                        $product->save();

                    }else{
                        Log::error("IyzicoController::saveProduct() - Product could not be created. Product : " . json_encode($newProduct));
                        //return back()->with(['message' => __('(iyzico) Product could not be created. Product : ') . $productName, 'type' => 'error']);
                    }
                }
            }


            ////////// PRICING PLAN //////////


            //check if price exists
            if($product->price_id != null){
                //Price exists

                // One-Time price
                if($type == "o"){
                    
                    // iyzico handles one time prices with payments, so we do not need to set anything for one-time payments.
                    $product->price_id = __('Not Needed');
                    $product->save();
                    
                }else{
                    // Subscription

                    // check if price exists in iyzico
                    $prd = json_decode(json_encode([
                        "pricingPlanReferenceCode" => $product->price_id,
                    ]));

                    $checkPrice = $iyzipayActions->retrieveSubscriptionPricingPlan($prd);

                    if($checkPrice->getReferenceCode() != null){
                        //Price exists
                        //Log::info("iyzico price exists");
                    }else{
                        //Price does not exist
                        //Log::info("iyzico price does not exist");

                        // get old price id
                        $oldPricingPlanId = $product->price_id;

                        // create new plan with new values
                        $interval = $frequency == "m" ? 'MONTHLY' : 'YEARLY';

                        if($plan->trial_days != "undefined"){
                            $trials = $plan->trial_days ?? 0;
                        }else{
                            $trials = 0;
                        }

                        // $this->iyzipayActions->deleteSubscriptionPricingPlan($oldBillingPlanId); -> Moved to updateUserData() function

                        $pricingPlan = json_decode(json_encode([
                            'paymentInterval' => $interval,
                            'paymentIntervalCount' => 1,
                            'paymentType' => 'RECURRING',
                            'trialPeriodDays' => $trials,
                            'productReferenceCode' => $product->product_id,
                            'price' => BigDecimal::of($price)->toScale(2),
                            'name' => $product->plan_name,
                        ]));

                        //Log::info("SubscriptionCreatePricingPlanRequest : " . json_encode($pricingPlan));

                        $subscriptionPricingPlan = $iyzipayActions->createSubscriptionPricingPlan($pricingPlan);

                        if($subscriptionPricingPlan->getReferenceCode() == null){
                            Log::error("IyzicoController::saveProduct() - Pricing Plan could not be created. Pricing Plan : " . json_encode($subscriptionPricingPlan));
                            //return back()->with(['message' => __('(iyzico) Pricing Plan could not be created. Pricing Plan : ') . $product->plan_name, 'type' => 'error']);
                        }else{

                            //Log::info("iyzico price refreshed");

                            $product->price_id = $subscriptionPricingPlan->getReferenceCode();
                            $product->save();

                            $history = new OldGatewayProducts();
                            $history->plan_id = $planId;
                            $history->plan_name = $productName;
                            $history->gateway_code = 'iyzico';
                            $history->product_id = $product->product_id;
                            $history->old_product_id = $oldProductId;
                            $history->old_price_id = $oldPricingPlanId;
                            $history->new_price_id = $subscriptionPricingPlan->getReferenceCode();
                            $history->status = 'check';
                            $history->save();

                            $tmp = self::updateUserData();
                        }

                    }

                    ///////////// To support old entries and prevent update issues on trial and non-trial areas
                    ///////////// update system is cancelled. instead we are going to create new ones, deactivate old ones and replace them.

                }

            }else{
                // price_id is null so we need to create plans

                // One-Time price
                if($type == "o"){
                    
                    // iyzico handles one time prices with orders, so we do not need to set anything for one-time payments.
                    $product->price_id = __('Not Needed');
                    $product->save();
                    
                }else{
                    // Subscription


                    $interval = $frequency == "m" ? 'MONTHLY' : 'YEARLY';

                    if($plan->trial_days != "undefined"){
                        $trials = $plan->trial_days ?? 0;
                    }else{
                        $trials = 0;
                    }

                    $pricingPlan = json_decode(json_encode([
                        'paymentInterval' => $interval,
                        'paymentIntervalCount' => 1,
                        'paymentType' => 'RECURRING',
                        'trialPeriodDays' => $trials,
                        'productReferenceCode' => $product->product_id,
                        'price' => BigDecimal::of($price)->toScale(2), // number_format($price, 2),
                        'name' => $product->plan_name,
                    ]));

                    //Log::info("SubscriptionCreatePricingPlanRequest : " . json_encode($pricingPlan));

                    $subscriptionPricingPlan = $iyzipayActions->createSubscriptionPricingPlan($pricingPlan);

                    if($subscriptionPricingPlan->getReferenceCode() == null){
                        Log::error("IyzicoController::saveProduct() - Pricing Plan could not be created. Pricing Plan : " . json_encode($subscriptionPricingPlan));
                        //return back()->with(['message' => __('(iyzico) Pricing Plan could not be created. Pricing Plan : ') . $product->plan_name, 'type' => 'error']);
                    }else{

                        //Log::info("iyzico price created");

                        $product->price_id = $subscriptionPricingPlan->getReferenceCode();
                        $product->save();
                    }
                }
            }

        }catch(\Exception $ex){
            Log::error("IyzicoController::saveProduct()\n".$ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }

    } // saveProduct() end


    /**
     * Used to generate new product id and price id of all saved membership plans in iyzico gateway.
     */
    public static function saveAllProducts(){
        try{

            $gateway = Gateways::where("code", "iyzico")->first();
            if($gateway == null) { 
                return back()->with(['message' => __('Please enable iyzico'), 'type' => 'error']);
                abort(404); } 

            // Get all membership plans

            $plans = PaymentPlans::where('active', 1)->get();

            foreach ($plans as $plan) {
                // Replaced definitions here. Because if monthly or prepaid words change just updating here will be enough.
                $freq = $plan->frequency == "monthly" ? "m" : "y"; // m => month | y => year
                $typ = $plan->type == "prepaid" ? "o" : "s"; // o => one-time | s => subscription

                self::saveProduct($plan->id, $plan->name, $plan->price, $freq, $typ);
            }

            // Create webhook of iyzico
            // TODO: $tmp = self::createWebhook();

        }catch(\Exception $ex){
            Log::error("IyzicoController::saveAllProducts()\n".$ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }

    } // saveAllProducts() end


    /**
     * Displays Buyer info page of iyzico gateway for prepaid plans.
     */
    public static function prepaid($planId, $plan, $incomingException = null){

        $couponCode = request()->input('coupon');
        if($couponCode){
            $coupone = Coupon::where('code', $couponCode)->first();
        }else{
            $coupone = null;
        }

        $newDiscountedPrice = $plan->price;
        if($coupone){
            $newDiscountedPrice  = $plan->price - ($plan->price * ($coupone->discount / 100));
            if ($newDiscountedPrice != floor($newDiscountedPrice)) {
                $newDiscountedPrice = number_format($newDiscountedPrice, 2);
            }
        }

        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        $exception = $incomingException;

        try {
            if(self::getIyzicoPriceId($planId) == null){
                $exception = "Product ID is not set! Please save Membership Plan again.";
            }

        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(),':');
        }
        
        return view('panel.user.payment.prepaid.payWithIyzicoPrepare', compact('plan', 'newDiscountedPrice','gateway', 'exception', 'currency'));
    }


    /**
     * Displays Payment Page (checkoutform) of iyzico gateway for prepaid plans.
     */
    public static function prepaidPay(Request $request){

        $previousRequest = app('request')->create(url()->previous());

        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        $plan = null;
        $exception = "";
        $checkoutform = null;


        try {

            if($request == null){
                return back()->with(['message' => __('Please fill all fields'), 'type' => 'error']);
            }

                // check request data for form data for buyer and address and planid
                if(
                    $request->planId == null || 
                    $request->name == null || 
                    $request->surname == null || 
                    $request->identityNumber == null || 
                    $request->email == null || 
                    $request->gsmNumber == null ||
                    $request->registrationAddress == null || 
                    $request->city == null || 
                    $request->country == null || 
                    $request->zipCode == null ||
                    $request->ip == null)
                {
                    // Log::error("IyzicoController::prepaidPay() - Missing fields - User ID: " . Auth::user()->id);
                    // Log::info("PlanId : " . $request->planId);
                    // Log::info("Name : " . $request->name);
                    // Log::info("Surname : " . $request->surname);
                    // Log::info("IdentityNumber : " . $request->identityNumber);
                    // Log::info("Email : " . $request->email);
                    // Log::info("GsmNumber : " . $request->gsmNumber);
                    // Log::info("RegistrationAddress : " . $request->registrationAddress);
                    // Log::info("City : " . $request->city);
                    // Log::info("Country : " . $request->country);
                    // Log::info("ZipCode : " . $request->zipCode);
                    // Log::info("Ip : " . $request->ip);
                    return back()->with(['message' => __('Please fill all fields'), 'type' => 'error']);
                }
                
                // create iyzipayActions class
                $iyzipayActions = self::getIyzipayActions();

                // create a new instance of incoming $request for buyer
                $buyerRequest = json_decode(json_encode([
                    "id" => Auth::user()->id,
                    "planId" => $request->planId,
                    "name" => $request->name,
                    "surname" => $request->surname,
                    "identityNumber" => $request->identityNumber,
                    "email" => $request->email,
                    "gsmNumber" => $request->gsmNumber,
                    "registrationAddress" => $request->registrationAddress,
                    "city" => $request->city,
                    "country" => $request->country,
                    "zipCode" => $request->zipCode,
                    "ip" => $request->ip,
                ]));
            

                // create buyer from request data
                $buyer = $iyzipayActions->createBuyer($buyerRequest);


                // create a new instance of incoming $request for address
                $addressRequest = json_decode(json_encode([
                    "contactName" => $request->name . " " . $request->surname,
                    "address" => $request->registrationAddress,
                    "city" => $request->city,
                    "country" => $request->country,
                    "zipCode" => $request->zipCode,
                ]));


                // create address from request data
                $address = $iyzipayActions->createAddress($addressRequest);

                // get plan
                $plan = PaymentPlans::where('id', $request->planId)->first();

                $basketItemsArray = array();

                $newDiscountedPrice = $plan->price;
                if ($previousRequest->has('coupon')) {
                    $coupon = Coupon::where('code', $previousRequest->input('coupon'))->first();
                    if($coupon){
                        $newDiscountedPrice  = $plan->price - ($plan->price * ($coupon->discount / 100));

                        session_start(); // Start the session if not already started
                        $_SESSION['applied_coupon'] = [
                            'coupon' => $coupon,
                            'plan_id' => $plan->id,
                        ];
                        session_write_close(); // Close the session
                    }
                }

                $basketItems = 
                    [
                        "basketItemId" => $plan->id,
                        "name" => $plan->name,
                        "category1" => "Token Packs",
                        "itemType" => "VIRTUAL",
                        "price" => $newDiscountedPrice,
                    ];
                $basketItem_0 = $iyzipayActions->createBasketItem($basketItems);

                // now we have everthing to create one time payment. Sum them to one request
                $paymentRequest = json_decode(json_encode([
                    "price" => $newDiscountedPrice,
                    "paidPrice" => $newDiscountedPrice,
                    "paymentGroup" => "PRODUCT",
                    "callbackUrl" => route('dashboard.user.payment.iyzico.prepaid.callback'),
                    "enabledInstallments" => [1, 2, 3, 6, 9],
                    "buyer" => $buyer,
                    "shippingAddress" => $address,
                    "billingAddress" => $address,
                    "basketItems" => $basketItemsArray,
                ]));

                // create checkout form for one time payment with paymentRequest
                $requestOneTimePayment = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();

                $requestOneTimePayment->setPrice($newDiscountedPrice);
                $requestOneTimePayment->setPaidPrice($newDiscountedPrice);
                $requestOneTimePayment->setCallbackUrl(route('dashboard.user.payment.iyzico.prepaid.callback'));
                $requestOneTimePayment->setEnabledInstallments(array(1, 2, 3, 6, 9));
                $requestOneTimePayment->setBuyer($buyer);
                $requestOneTimePayment->setShippingAddress($address);
                $requestOneTimePayment->setBillingAddress($address);
                $requestOneTimePayment->setBasketItems(array($basketItem_0));

                

                $checkoutform = \Iyzipay\Model\CheckoutFormInitialize::create($requestOneTimePayment, $iyzipayActions->getConfig());

                if ($checkoutform->getStatus() === 'failure') {
                    $errorCode = $checkoutform->getErrorCode();
                    $errorMessage = $checkoutform->getErrorMessage();
                    return back()->with([
                        'message' => __('Please enter valid information!') . " Error Code: $errorCode - $errorMessage",
                        'type' => 'error',
                    ]);
                }
                
                // function did not work out for now. may be we can turn back after
                //$checkoutform = $iyzipayActions->createOneTimePayment($paymentRequest);


                //Since we can not transfer anything except token id to callback page we must use a middle step
                // We are going to save token id to CustomSettings table and retrieve it from callback page.
                $customSettings = new CustomSettings();
                $customSettings->key = $checkoutform->getToken();
                $customSettings->value_str = strval($plan->id);
                $customSettings->save();

            


        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(),':');
        }
        
        return view('panel.user.payment.prepaid.payWithIyzico', compact('plan','newDiscountedPrice', 'gateway', 'exception', 'currency', 'checkoutform'));
    }



    /**
     * Displays result of payment and saves payment data to database if payment is successful.
     */
    public static function prepaidCallback(Request $request){


        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        $plan = null;
        $exception = null;

        // check if request has token
        if($request->token == null){
            return back()->with(['message' => __('Token is missing'), 'type' => 'error']);
        }

        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        // create request of one time payment result
        $checkoutRequest = [
            "token" => $request->token,
        ];

        // retrieve one time payment result
        $checkoutresult = $iyzipayActions->retrieveOneTimePayment($checkoutRequest);

        if($checkoutresult->getStatus() == 'success' && ($checkoutresult->getPaymentStatus() == 'SUCCESS' || $checkoutresult->getPaymentStatus() == 'success')){




            // get settings
            $settings = Setting::first();
            
            $user = Auth::user();

            // Since we could not transfer anything except token id to callback page we must use a middle step
            // We saved token id to CustomSettings table and retrieve it now
            $customSettings = CustomSettings::where('key', $request->token)->first();
            if($customSettings == null){
                // if we can't get plan id, just save it with a warning. So user can check from iyzico backend.
                $payment = new UserOrder();
                $payment->order_id = $checkoutresult->getPaymentId();
                $payment->plan_id = "Missing Plan Id. Check with token and order id.";
                $payment->type = 'prepaid';
                $payment->user_id = $user->id;
                $payment->payment_type = 'iyzico';
                $payment->price = 0;
                $payment->affiliate_earnings = 0;
                $payment->status = 'Success with token:' . $request->token;
                $payment->country = $user->country ?? 'Unknown';
                $payment->save();
                return back()->with(['message' => __('Token is missing'), 'type' => 'error']);
            }

            // get plan id from CustomSettings table
            $planId = $customSettings->value_str;

            // get plan
            $plan = PaymentPlans::where('id', $planId)->first();


            $newDiscountedPrice = $plan->price;
            session_start(); // Start the session if not already started
            if (isset($_SESSION['applied_coupon'])) {
                $appliedCouponData = $_SESSION['applied_coupon'];
                if ($appliedCouponData['plan_id'] == $planId) {
                    $appliedCoupon = $appliedCouponData['coupon'];
                    $newDiscountedPrice = $plan->price - ($plan->price * ($appliedCoupon->discount / 100));
                }
                unset($_SESSION['applied_coupon']);
            }
            session_write_close();

            // save checkout to orders
            $payment = new UserOrder();
            $payment->order_id = $checkoutresult->getPaymentId();
            $payment->plan_id = $plan->id;
            $payment->type = 'prepaid';
            $payment->user_id = $user->id;
            $payment->payment_type = 'iyzico';
            $payment->price = $newDiscountedPrice;
            $payment->affiliate_earnings = ($newDiscountedPrice*$settings->affiliate_commission_percentage)/100;
            $payment->status = 'Success';
            $payment->country = $user->country ?? 'Unknown';
            $payment->save();

            $plan->total_words == -1? ($user->remaining_words = -1) : ($user->remaining_words += $plan->total_words);
            $plan->total_images == -1? ($user->remaining_images = -1) : ($user->remaining_images += $plan->total_images);

            $user->save();

            // delete custom settings since we do not need it anymore
            $customSettings->delete();

            createActivity($user->id, __('Purchased'), $plan->name.' '. __('Token Pack'), null);

        }

        return view('panel.user.payment.prepaid.payWithIyzicoResult', compact('plan', 'gateway', 'exception', 'currency', 'checkoutresult'));

    }


    /**
     * Displays Payment Page of Stripe gateway.
     */
    public static function subscribe($planId, $plan, $incomingException = null){

        $couponCode = request()->input('coupon');
        if($couponCode){
            $coupone = Coupon::where('code', $couponCode)->first();
        }else{
            $coupone = null;
        }

        $newDiscountedPrice = $plan->price;
        if($coupone){
            $newDiscountedPrice  = $plan->price - ($plan->price * ($coupone->discount / 100));
            if ($newDiscountedPrice != floor($newDiscountedPrice)) {
                $newDiscountedPrice = number_format($newDiscountedPrice, 2);
            }
        }


        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;
        $exception = $incomingException;
        $iyzicoPriceId = self::getIyzicoPriceId($planId);
        try {
            if($iyzicoPriceId == null){
                $exception = "Product ID is not set! Please save Membership Plan again.";
            }
        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(),':');
        }
        
        return view('panel.user.payment.subscription.payWithIyzicoPrepare', compact('plan','newDiscountedPrice', 'gateway', 'exception', 'currency', 'iyzicoPriceId'));

    }

    /**
     * Displays Payment Page (checkoutform) of iyzico gateway for subscription plans.
     */
    public static function subscribePay(Request $request){
        $previousRequest = app('request')->create(url()->previous());

        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        $plan = null;
        $exception = "";
        $checkoutform = null;

        // log info request all to check incoming data
        //Log::info(json_encode($request->all()));

        try {
            if($request == null){
                return back()->with(['message' => __('Please fill all fields'), 'type' => 'error']);
            }
            // check request data for form data for buyer and address and planid
            if(
                $request->planId == null || 
                $request->name == null || 
                $request->surname == null || 
                $request->identityNumber == null || 
                $request->email == null || 
                $request->gsmNumber == null ||
                $request->registrationAddress == null || 
                $request->city == null || 
                $request->country == null || 
                $request->zipCode == null ||
                $request->iyzicoPriceId == null ||
                $request->ip == null)
            {
                return back()->with(['message' => __('Please fill all fields'), 'type' => 'error']);
            }

            $plan = PaymentPlans::where('id', $request->planId)->first();
            
            // create iyzipayActions class
            $iyzipayActions = self::getIyzipayActions();

            $user = Auth::user();

            // create a new instance of incoming $request for subscription customer
            $customerRequest = json_decode(json_encode([
                //"id" => $user->id,
                "name" => $request->name,
                "surname" => $request->surname,
                "identityNumber" => $request->identityNumber,
                "email" => $request->email,
                "gsmNumber" => $request->gsmNumber,
                "shippingContactName" => $request->name . " " . $request->surname,
                "shippingCity" => $request->city,
                "shippingCountry" => $request->country,
                "shippingAddress" => $request->registrationAddress,
                "shippingZipCode" => $request->zipCode,
                "billingContactName" => $request->name . " " . $request->surname,
                "billingCity" => $request->city,
                "billingCountry" => $request->country,
                "billingAddress" => $request->registrationAddress,
                "billingZipCode" => $request->zipCode,
            ]));
            
            if($user->iyzico_id != null){
                // retrieve customer from iyzico
                $cst = json_decode(json_encode([
                    "customerReferenceCode" => $user->iyzico_id,
                ]));
                $customer = $iyzipayActions->retrieveSubscriptionCustomer($cst);
                if($customer->getReferenceCode() != null){
                    // customer exists
                    Log::info("iyzico customer exists");
                    $customerRequest->customerReferenceCode = $user->iyzico_id;
                    $customer = $iyzipayActions->updateSubscriptionCustomer($customerRequest);
                }else{
                    // customer does not exist
                    Log::info("iyzico customer does not exist");
                    $customer = $iyzipayActions->createCustomer($customerRequest);
                }
            }else{
                // create customer from request data
                Log::info("iyzico customer does not exist");
                $customer = $iyzipayActions->createCustomer($customerRequest);
            }
            // check if customer set
            if($customer == null){
                return back()->with(['message' => __('Customer could not set'), 'type' => 'error']); 
            }

            $newDPriceID = $request->iyzicoPriceId;
            $newDiscountedPrice  = $plan->price;
            if ($previousRequest->has('coupon')) 
            {
                $coupon = Coupon::where('code', $previousRequest->input('coupon'))->first();
                if($coupon){
                    $prd = json_decode(json_encode([
                        "name"          => "discount_" . $coupon->code . "_" . $user->id . "_" . $plan->id . "_" . time(),
                    ]));

                    $newProduct = $iyzipayActions->createSubscriptionProduct($prd);

                    if($newProduct->getReferenceCode() != null)
                    {
                        $newDiscountedPrice  = $plan->price - ($plan->price * ($coupon->discount / 100));
                        $interval = $plan->frequency == "monthly" ? 'MONTHLY' : 'YEARLY';

                        if($plan->trial_days != "undefined"){
                            $trials = $plan->trial_days ?? 0;
                        }else{
                            $trials = 0;
                        }

                        $pricingPlan = json_decode(json_encode([
                            'paymentInterval' => $interval,
                            'paymentIntervalCount' => 1,
                            'paymentType' => 'RECURRING',
                            'trialPeriodDays' => $trials,
                            'productReferenceCode' => $newProduct->getReferenceCode(),
                            'price' => BigDecimal::of($newDiscountedPrice)->toScale(2),
                            'name' => $prd->name,
                        ]));

                        $subscriptionPricingPlan = $iyzipayActions->createSubscriptionPricingPlan($pricingPlan);

                        if($subscriptionPricingPlan->getReferenceCode() == null){
                            Log::error("IyzicoController::saveProduct() - Pricing Plan could not be created. Pricing Plan : " . json_encode($subscriptionPricingPlan));
                        }

                        $newDPriceID = $subscriptionPricingPlan->getReferenceCode();
                    }

                    session_start(); 
                    $_SESSION['applied_coupon'] = [
                        'coupon' => $coupon,
                        'plan_id' => $plan->id,
                    ];
                    session_write_close(); 

                }
            }


            $checkoutFormRequest = new \Iyzipay\Request\Subscription\SubscriptionCreateCheckoutFormRequest();
            $checkoutFormRequest->setConversationId($iyzipayActions->generateRandomNumber());
            $checkoutFormRequest->setLocale($iyzipayActions->getLocale());
            $checkoutFormRequest->setPricingPlanReferenceCode($newDPriceID);
            $checkoutFormRequest->setSubscriptionInitialStatus("ACTIVE");
            $checkoutFormRequest->setCallbackUrl(route('dashboard.user.payment.iyzico.subscribe.callback'));
            $checkoutFormRequest->setCustomer($customer);


            $checkoutform = \Iyzipay\Model\Subscription\SubscriptionCreateCheckoutForm::create($checkoutFormRequest, $iyzipayActions->getConfig());

            if ($checkoutform->getStatus() === 'failure') {
                $errorCode = $checkoutform->getErrorCode();
                $errorMessage = $checkoutform->getErrorMessage();
                return back()->with([
                    'message' => __('Please enter valid information!') . " Error Code: $errorCode - $errorMessage",
                    'type' => 'error',
                ]);
            }

            Log::info("checkoutform : " . json_encode($checkoutform));

            if($checkoutform == null){
                $exception = "Checkout form could not be created";
                return back()->with(['message' => __('Please enter valid information!'), 'type' => 'error']);
            }

            
   
            //Since we can not transfer anything except token id to callback page we must use a middle step
            // We are going to save token id to CustomSettings table and retrieve it from callback page.
            $customSettings = new CustomSettings();
            $customSettings->key = $checkoutform->getToken();
            $customSettings->value_str = strval($request->planId);
            $customSettings->save();

        } catch (\Exception $th) {
            $exception = $th->getMessage();
            //$exception = Str::before($th->getMessage(),':');
        }
        
        return view('panel.user.payment.subscription.payWithIyzico', compact('plan', 'newDiscountedPrice','gateway', 'exception', 'currency', 'checkoutform'));

    }


        /**
     * Displays result of payment and saves payment data to database if payment is successful.
     */
    public static function subscribeCallback(Request $request){

        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        $plan = null;
        $exception = null;

        // check if request has token
        if($request->token == null){
            return back()->with(['message' => __('Token is missing'), 'type' => 'error']);
        }

        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        // retrieve subscription result
        $checkoutresultrequest = new \Iyzipay\Request\Subscription\RetrieveSubscriptionCreateCheckoutFormRequest();
        $checkoutresultrequest->setCheckoutFormToken(strval($request->token));
        $checkoutresult = \Iyzipay\Model\Subscription\RetrieveSubscriptionCheckoutForm::retrieve($checkoutresultrequest, $iyzipayActions->getConfig());


        if($checkoutresult->getStatus() == 'success' && ($checkoutresult->getSubscriptionStatus() == 'ACTIVE' || $checkoutresult->getSubscriptionStatus() == 'active')){

            // get settings
            $settings = Setting::first();
            
            $user = Auth::user();

            // Since we could not transfer anything except token id to callback page we must use a middle step
            // We saved token id to CustomSettings table and retrieve it now
            $customSettings = CustomSettings::where('key', $request->token)->first();
            if($customSettings == null){
                // if we can't get plan id, just save it with a warning. So user can check from iyzico backend.
                $payment = new UserOrder();
                $payment->order_id = $checkoutresult->getReferenceCode();
                $payment->plan_id = "Missing Plan Id. Check with token and order id.";
                $payment->type = 'prepaid';
                $payment->user_id = $user->id;
                $payment->payment_type = 'iyzico';
                $payment->price = 0;
                $payment->affiliate_earnings = 0;
                $payment->status = 'Success with token:' . $request->token;
                $payment->country = $user->country ?? 'Unknown';
                $payment->save();
                return back()->with(['message' => __('Token is missing'), 'type' => 'error']);
            }

            // get plan id from CustomSettings table
            $planId = $customSettings->value_str;

            // get plan
            $plan = PaymentPlans::where('id', $planId)->first();

            $newDiscountedPrice = $plan->price;
            session_start(); // Start the session if not already started
            if (isset($_SESSION['applied_coupon'])) {
                $appliedCouponData = $_SESSION['applied_coupon'];
                if ($appliedCouponData['plan_id'] == $planId) {
                    $appliedCoupon = $appliedCouponData['coupon'];
                    $newDiscountedPrice = $plan->price - ($plan->price * ($appliedCoupon->discount / 100));
                }
                unset($_SESSION['applied_coupon']);
            }
            session_write_close();

            // save checkout to orders
            $payment = new UserOrder();
            $payment->order_id = $request->token;
            $payment->plan_id = $plan->id;
            $payment->type = 'subscription';
            $payment->user_id = $user->id;
            $payment->payment_type = 'iyzico';
            $payment->price = $newDiscountedPrice;
            $payment->affiliate_earnings = ($newDiscountedPrice*$settings->affiliate_commission_percentage)/100;
            $payment->status = 'Success';
            $payment->country = $user->country ?? 'Unknown';
            $payment->save();


            // get gateway product related to plan id
            $product = GatewayProducts::where(['plan_id' => $planId, 'gateway_code' => 'iyzico'])->first();


            // save subscription to database
            $subscription = new SubscriptionsModel();
            $subscription->user_id = $user->id;
            $subscription->name = $planId;
            $subscription->stripe_id = $checkoutresult->getReferenceCode();
            $subscription->stripe_status = "active"; // $plan->trial_days != 0 ? "trialing" : "AwaitingPayment";
            $subscription->stripe_price = $product->price_id;
            $subscription->quantity = 1;
            $subscription->trial_ends_at = $plan->trial_days != 0 ? \Carbon\Carbon::now()->addDays($plan->trial_days) : null;
            $subscription->ends_at = $plan->trial_days != 0 ? \Carbon\Carbon::now()->addDays($plan->trial_days) : \Carbon\Carbon::now()->addDays(30);
            $subscription->plan_id = $planId;
            $subscription->paid_with = 'iyzico';
            $subscription->save();

            $subscriptionItem = new SubscriptionItems();
            $subscriptionItem->subscription_id = $subscription->id;
            $subscriptionItem->stripe_id = $checkoutresult->getParentReferenceCode();
            $subscriptionItem->stripe_product = $product->product_id;
            $subscriptionItem->stripe_price = $product->price_id;
            $subscriptionItem->quantity = 1;
            $subscriptionItem->save();


            $plan->total_words == -1? ($user->remaining_words = -1) : ($user->remaining_words += $plan->total_words);
            $plan->total_images == -1? ($user->remaining_images = -1) : ($user->remaining_images += $plan->total_images);

            $user->save();

            // delete custom settings since we do not need it anymore
            $customSettings->delete();

            createActivity($user->id, __('Subscribed'), $plan->name.' '. __('Plan'), null);

        }


        return view('panel.user.payment.subscription.payWithIyzicoResult', compact('plan', 'gateway', 'exception', 'currency', 'checkoutresult'));

    }



    /**
     * Cancels current subscription plan
     */
    public static function subscribeCancel(){

        $user = Auth::user();

        $userId=$user->id;

        $gateway = Gateways::where("code", "iyzico")->first();
        if($gateway == null) { abort(404); } 

        // Get current active subscription
        $activeSub = SubscriptionsModel::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();

        if($activeSub != null){
            $plan = PaymentPlans::where('id', $activeSub->plan_id)->first();

            // get iyzipayActions class
            $iyzipayActions = self::getIyzipayActions();

            // cancel subscription
            $cancelSubscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $activeSub->stripe_id,
            ]));

            $cancelSubscription = $iyzipayActions->cancelSubscription($cancelSubscriptionRequest);

            if($cancelSubscription != null){
                $activeSub->stripe_status = "cancelled";
                $activeSub->ends_at = \Carbon\Carbon::now();
                $activeSub->save();

                $recent_words = $user->remaining_words - $plan->total_words;
                $recent_images = $user->remaining_images - $plan->total_images;


                $user->remaining_words = $recent_words < 0 ? 0 : $recent_words;
                $user->remaining_images = $recent_images < 0 ? 0 : $recent_images;
                $user->save();

                createActivity($user->id, 'Cancelled', 'Subscription plan', null);

                return back()->with(['message' => 'Your subscription is cancelled succesfully.', 'type' => 'success']);
            }else{
                return back()->with(['message' => 'Your subscription could not be cancelled.', 'type' => 'error']);
            }



        }


    }

    // function that returns days left from now to given timestamp, if timestamp is null or days left is less than 0, returns null
    public static function getDaysLeft($timestamp){
        if($timestamp == null){
            return null;
        }
        // Convert millisecond timestamp to seconds as iyzico sends timestamp in milliseconds
        $timestampInSeconds = $timestamp / 1000;
        $now = Carbon::now();
        $ends = Carbon::createFromTimestamp($timestampInSeconds);
        $daysLeft = $now->diffInDays($ends, false);
        if($daysLeft < 0){
            return null;
        }
        return $daysLeft;
    }


    public static function getSubscriptionDaysLeft(){

        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        $userId=Auth::user()->id;

        // Get current active subscription
        $activeSub = SubscriptionsModel::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();
        if($activeSub != null){
            $plan = PaymentPlans::where('id', $activeSub->plan_id)->first();

            // get subscription
            $subscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $activeSub->stripe_id,
            ]));

            $subscription = $iyzipayActions->getSubscriptionDetails($subscriptionRequest);

            if(Str::lower($subscription->getSubscriptionStatus()) == 'active'){

                if($subscription->getTrialEndDate()){
                    $trialDaysLeft = self::getDaysLeft($subscription->getTrialEndDate());
                    if($trialDaysLeft != null){
                        return $trialDaysLeft;
                    }
                }else{

                    $orders = $subscription->getOrders();

                    //Log::info("getSubscriptionDaysLeft() -> orders : " . json_encode($orders));

                    for($i = 0; $i < count($orders); $i++){
                        if($orders[$i]->orderStatus == "WAITING"){
                            
                        }else{
                            
                            $daysLeft = self::getDaysLeft($orders[$i]->endPeriod);
                            if($daysLeft != null){
                                return $daysLeft;
                            }else{
                                return 0;
                            }
                            
                            break;
                        }
                    }
                }

            }else{
                return 0;
            }

        }
        return null;
    }



    public static function checkIfTrial(){
        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        $userId=Auth::user()->id;

        // Get current active subscription
        $activeSub = SubscriptionsModel::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();
        if($activeSub != null){
            $plan = PaymentPlans::where('id', $activeSub->plan_id)->first();

            // get subscription
            $subscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $activeSub->stripe_id,
            ]));

            $subscription = $iyzipayActions->getSubscriptionDetails($subscriptionRequest);

            if(Str::lower($subscription->getSubscriptionStatus()) == 'active'){

                if($subscription->getTrialEndDate()){
                    $trialDaysLeft = self::getDaysLeft($subscription->getTrialEndDate());
                    if($trialDaysLeft != null){
                        return true;
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }

            }else{
                return false;
            }

        }
        return false;
    }

    public static function getSubscriptionRenewDate(){

        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        $userId=Auth::user()->id;

        // Get current active subscription
        $activeSub = SubscriptionsModel::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();
        if($activeSub != null){
            $plan = PaymentPlans::where('id', $activeSub->plan_id)->first();

            // get subscription
            $subscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $activeSub->stripe_id,
            ]));

            $subscription = $iyzipayActions->getSubscriptionDetails($subscriptionRequest);

            if(Str::lower($subscription->getSubscriptionStatus()) == 'active'){


                $orders = $subscription->getOrders();

                for($i = 0; $i < count($orders); $i++){
                    if($orders[$i]->orderStatus == "WAITING"){
                        
                    }else{
                        return \Carbon\Carbon::createFromTimestamp($orders[$i]->endPeriod/1000)->format('F jS, Y');
                        break;
                    }
                }


                
                return \Carbon\Carbon::now()->format('F jS, Y');
                

            }else{
                $activeSub->stripe_status = "cancelled";
                $activeSub->ends_at = \Carbon\Carbon::now();
                $activeSub->save();
                return \Carbon\Carbon::now()->format('F jS, Y');
            }

        }
        return null;

    }

    
    /**
     * Checks status directly from gateway and updates database if cancelled or suspended.
     */
    public static function getSubscriptionStatus(){
        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        $userId=Auth::user()->id;

        // Get current active subscription
        $activeSub = SubscriptionsModel::where([['stripe_status', '=', 'active'], ['user_id', '=', $userId]])->orWhere([['stripe_status', '=', 'trialing'], ['user_id', '=', $userId]])->first();
        if($activeSub != null){
            $plan = PaymentPlans::where('id', $activeSub->plan_id)->first();

            // get subscription
            $subscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $activeSub->stripe_id,
            ]));

            $subscription = $iyzipayActions->getSubscriptionDetails($subscriptionRequest);

            if(Str::lower($subscription->getSubscriptionStatus()) == 'active'){
                return true;
            }else{
                $activeSub->stripe_status = 'cancelled';
                $activeSub->ends_at = \Carbon\Carbon::now();
                $activeSub->save();
                return false;
            }
        }
        return null;
    }


    public static function cancelSubscribedPlan($planId, $subsId){

        $user = Auth::user();

        // get iyzipayActions class
        $iyzipayActions = self::getIyzipayActions();

        $currentSubscription = SubscriptionsModel::where('id', $subsId)->first();

        if($currentSubscription != null){
            $plan = PaymentPlans::where('id', $planId)->first();

            // cancel subscription
            $cancelSubscriptionRequest = json_decode(json_encode([
                "subscriptionReferenceCode" => $currentSubscription->stripe_id,
            ]));

            $cancelSubscription = $iyzipayActions->cancelSubscription($cancelSubscriptionRequest);

            if($response == ""){
                $currentSubscription->stripe_status = "cancelled";
                $currentSubscription->ends_at = \Carbon\Carbon::now();
                $currentSubscription->save();
                return true;
            }

        }

        return false;
    }

    /**
     * Since price id is changed, we must update user data, i.e cancel current subscriptions for old price id.
     */
    public static function updateUserData(){

        $history = OldGatewayProducts::where([
            "gateway_code" => 'iyzico',
            "status" => 'check'
        ])->get();

        if($history != null){

            // get iyzipayActions class
            $iyzipayActions = self::getIyzipayActions();

            foreach ($history as $record) {

                // check record current status from gateway
                $lookingFor = $record->old_price_id; 

                // search subscriptions for record
                $subs = SubscriptionsModel::where([
                    'stripe_status' => 'active',
                    'stripe_price'  => $lookingFor 
                ])->get();

                if($subs != null){
                    foreach ($subs as $sub) {

                        // cancel subscription
                        $cancelSubscriptionRequest = json_decode(json_encode([
                            "subscriptionReferenceCode" => $sub->stripe_id,
                        ]));

                        $cancelSubscription = $iyzipayActions->cancelSubscription($cancelSubscriptionRequest);

                        // cancel subscription from our database
                        $sub->stripe_status = 'cancelled';
                        $sub->ends_at = \Carbon\Carbon::now();
                        $sub->save();
                    }
                }
                
                $record->status = 'checked';
                $record->save();

            }
        }

    }

    public static function iyzicoProductsList(){
        $iyzipayActions = self::getIyzipayActions();
        $req = json_decode(json_encode([
            "itemPage" => 1,
            "itemCount" => 100,
        ]));
        $products = $iyzipayActions->listSubscriptionProducts($req);
        return json_encode($products);
    }


    function verifyIncomingJson(Request $request){

        try{
            $gateway = Gateways::where("code", "iyzico")->first();
            if($gateway == null) { abort(404); }

            // below check mechanism is set from https://dev.iyzipay.com/tr/webhooks to check regular webhooks
            // but after consulting to customer service of iyzico, we learned that we get different json data from regular webhooks for recurring payments.
            // as of last mail - pasted below - we can not use this validation mechanism for recurring payments. 
            // hence we can only check if incoming json is valid - contains all fields - or not. 
            //-------------------------------------------------------
            /*
                Fatih Bey Merhaba,

                Webhook için farklı bir dokümanımız maalesef bulunmamaktadır. Mevcut standart webhook bildirimi için validasyon gerçekleştirebilirsiniz ancak subscription tekrarlı ödemelerin webhook bildiriminde dönen değerler farklı olduğundan dolayı bu kısımda ek olarak bir doküman bulunmamaktadır.

                https://docs.iyzico.com/ek-servisler/webhook

                https://dev.iyzipay.com/tr/webhooks

                Saygılarımla
                Metecan Kıyıcı
                iyzico
            */
            //-------------------------------------------------------

            $payload = json_decode($request->getContent());
            if(!$payload->orderReferenceCode || !$payload->customerReferenceCode || !$payload->subscriptionReferenceCode || !$payload->iyziReferenceCode || !$payload->iyziEventType || !$payload->iyziEventTime){
                return false;
            }

            if(Carbon::parse($currentSubscription->created_at)->diffInMinutes(Carbon::parse($newData->create_time)) < 5 ){
                return false;
            }


            return true;

            /// below code is not applicable for recurring payments, please see the comment above

            if($request->hasHeader('X-IYZ-SIGNATURE') == true){
                $incoming_signature = $request->header('X-IYZ-SIGNATURE');
            }else{
                return false;
            }

            $payload = json_decode($request->getContent());

            // get secret key from gateway according to mode
            $secretKey = $gateway->mode == 'sandbox' ? $gateway->sandbox_client_secret : $gateway->live_client_secret;

            // get iyziEventType from payload
            if($payload->iyziEventType){
                $iyziEventType = $payload->iyziEventType;
            }else{
                return false;
            }

            // get iyziReferenceCode from payload
            if($payload->iyziReferenceCode){
                $iyziReferenceCode = $payload->iyziReferenceCode;
            }else{
                return false;
            }

            // Concatenate the values
            $stringToBeHashed = $secretKey . $iyziEventType . $iyziReferenceCode;

            //Log::info("stringToBeHashed : " . $stringToBeHashed);

            // Hash the concatenated string using SHA-1 and then base64 encode it
            $hash = base64_encode(sha1($stringToBeHashed, true));

            //Log::info("hash : " . $hash);

            // Compare the hash with the incoming signature
            return $hash == $incoming_signature ? true : false;

        } catch (\Exception $th) {
            error_log("(Webhooks) IyzicoController::verifyIncomingJson(): ".$th->getMessage());
        }

        return false;
    }

    public function handleWebhook(Request $request){

        //Log::info("handleWebhook() : " . json_encode($request->all()));

        $verified = self::verifyIncomingJson($request);

        if($verified == true){

            // Retrieve the JSON payload
            $payload = $request->getContent();

            // Fire the event with the payload
            event(new IyzicoWebhookEvent($payload));
        
            return response()->json(['success' => true]);
        
        }else{
            // Incoming json is NOT verified
            abort(404);
        }

    }


}
