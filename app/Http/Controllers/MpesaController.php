<?php
    namespace App\Http\Controllers;
    use App\Models\Order; // Replace with your Order model
use Illuminate\Http\Request;
use Paymentsds\MPesa\Client;
use Paymentsds\MPesa\Environment;
    class MpesaController extends Controller

    
    { 
         
        public function showPaymentForm() {
        return view('mpesa_payment');
    }
    public function initiatePayment($order_id)
{
    // Retrieve order details based on the provided order ID
    $order = Order::find($order_id);
    
    if (!$order) {
        // Handle the case where the order does not exist
        return redirect()->route('payment.failed', ['error' => 'Order not found']);
    }

    function formatKenyaPhoneNumber($phoneNumber) {
        // Convert the phone number to a string for easy manipulation
        $phoneNumberStr = (string)$phoneNumber;
    
        // Remove any leading '0' or "254"
        $phoneNumberStr = ltrim($phoneNumberStr, '0');
        $phoneNumberStr = ltrim($phoneNumberStr, '254');
    
        // Add '254' at the beginning of the phone number
        $formattedPhoneNumberStr = '254' . $phoneNumberStr;
        
        // Cast the formatted phone number string to an integer
        $formattedPhoneNumberInt = (int)$formattedPhoneNumberStr;
        
        return $formattedPhoneNumberInt;
    }

    // Extract the total amount and phone number from the order 
    $amount = (int)$order->total_amount;
    $phone_number = (int)$order->phone;
    $phone_number = formatKenyaPhoneNumber((int)$phone_number);


   
    $consumer_key = config('app.consumer_key');
    $consumer_secret = config('app.consumer_secret');
    $passkey = config('app.passkey');
    $shortcode = config('app.shortcode');
    
    // Get the access token
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    $credentials = base64_encode($consumer_key.':'.$consumer_secret);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials));
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $access_token = json_decode($result)->access_token;

    // Generate the timestamp and password
    $timestamp = date("YmdHis");
    $password = base64_encode($shortcode.$passkey.$timestamp);

    // Initiate the STK push
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$access_token));

    // Create the callback url
    $callback_url = config('app.callback_url');


    // Create the request data  
    $data = array(
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone_number,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phone_number, 
        'CallBackURL' => $callback_url,
        'AccountReference' => "MarriedToWine LC", // Use the order ID as AccountReference
        'TransactionDesc' => "Married2Wine",
        "MerchantRequestID"=> $order_id,
    );

    $data_string = json_encode($data);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    // Check the result and return a JSON data
    if ($status == 200) {
        request()->session()->flash('success','Payment request sent successfully. Please check your phone and enter your PIN.');
        return redirect()->route('PaymentConfirmation');
    } else {
        request()->session()->flash('error','Payment request failed. Please try again.');
        return redirect()->route('checkout');


        // $response = ["message" => "Payment request failed. Please try again."];
    }

    // return response()->json($response);
}

public function mpesaCallback(Request $request)
{
    // Extract relevant data from the callback JSON
    $post_data = file_get_contents("php://input");

  $data = json_decode($post_data, true);
  $log_file = fopen("mpesa.log", "a");
  fwrite($log_file, print_r($data, true));
  fclose($log_file);
  $data = $request->input('Body.stkCallback');

    // Ensure that the required fields are present in the JSON data
    $resultCode = $data['ResultCode'] ?? null;
    $resultDesc = $data['ResultDesc'] ?? null;
    $MerchantRequestID = $data['MerchantRequestID'] ?? null;
    // Find the order with the specified MerchantRequestID
    $order = Order::where('id', $MerchantRequestID)->first();
    if ($order) {
        if ($resultCode == 0) {
            // Payment was successful, update the order status
            // Check if the order status is not already 'completed'
            if ($order->payment_status != 'paid') {
                // Update the order status to 'completed' or any other status you desire
                $order->update([
                    'payment_status' => 'paid', // Update with the desired status
                    'status' => 'process',
                    'MpesaTransID' => $this->getCallbackItemValue($data, 'MpesaReceiptNumber'),
                    'MpesaTransAmount' => $this->getCallbackItemValue($data, 'Amount'),
                    'MpesaPhone' => $this->getCallbackItemValue($data, 'PhoneNumber'),
                    'MpesaFirstName' => null, // Add the appropriate field from the callback
                    // You can add more fields to update based on your requirements
                ]);

                // Additional logic can be added here based on your requirements
                return response()->json(['success' => 'Payment successfully processed.']);
            } else {
                // Order status is already 'completed', handle accordingly
                request()->session()->flash('info', 'Payment already processed for this order.');
                return redirect()->route('PaymentConfirmation');
            }
        } else {
            // Payment was not successful, handle accordingly
            request()->session()->flash('error', 'Payment failed. ' . $resultDesc);
            $response = ["message" => "Payment was not successful. " . $resultDesc];
            return response()->json($response);
        }
    } else {
        // If the order is not found, respond with an error status
        request()->session()->flash('error', 'Order not found.');
        $response = ["message" => "Order not found".$MerchantRequestID];
        return response()->json($response);
    }
}

private function getCallbackItemValue($data, $itemName)
{
    $value = null;

    if (isset($data['CallbackMetadata']['Item'])) {
        foreach ($data['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === $itemName) {
                $value = $item['Value'] ?? null;
                break;
            }
        }
    }

    return $value;
}

      
               
    }
    