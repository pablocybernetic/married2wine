<?php
    namespace App\Http\Controllers;
    use App\Models\Order; // Replace with your Order model
use Illuminate\Http\Request;
    class MpesaController extends Controller
    
    { 
        
        
        
   
        public function showPaymentForm() {
        return view('mpesa_payment');
    }
    public function initiatePayment($order_id)
    {
        // Retrieve order details based on the provided order ID
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
    // Extract the total amount and phone number from the order 
       $amount = (int)$order->total_amount;
       $phone_number = (int)$order->phone;
       $phone_number = formatKenyaPhoneNumber((int)$phone_number);


      
    

        
            // Define the credentials
            $consumer_key = "GNkLG2OnHSipzNyQkzRbCRfAaYDqJQKo";
            $consumer_secret = "4o9Xv8kGIF6gQMw8";
            $passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919"; // You need to get this from the Daraja
            $shortcode = "174379"; // You need to get this from the Daraja portal
    
            // Get the access token
            $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            $credentials = base64_encode($consumer_key.':'.$consumer_secret);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials)); //setting a custom header
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $access_token = json_decode($result)->access_token;
        
    
            // Get the user input
         
    
            // Generate the timestamp and password
$timestamp = date("YmdHis");
$password = base64_encode($shortcode.$passkey.$timestamp);

// Initiate the STK push
$url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$access_token)); //setting custom header

// Create the callback url
$callback_url = 'https://married2wine.com/mpesa/callback'; // You need to create this file

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
    'AccountReference' => "Test",
    'TransactionDesc' => "Test Payment"
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


                // $response = ["message" => "Payment request sent successfully. Please check your phone and enter your PIN."];
            } else {
                $response = ["message" => "Payment request failed. Please try again."];
            }
    
            return response()->json($response);
        }


       
        public function mpesaCallback(Request $request, $id)
        {
            // Log the callback data for debugging
            Log::info('M-Pesa Callback Data:', $request->all());
    
            // Implement your logic to process the callback data here
            // For example, update the order status based on the callback data
    
            // Retrieve the specific user based on the $id parameter
            $user = User::find($id);
    
            // Check if the user exists
            if ($user) {
                // Echo the callback data for the specific user
                echo "Callback data for User ID $id: " . json_encode($request->all());
            } else {
                // User not found
                echo "User with ID $id not found.";
            }
    
            // Respond to Safaricom with a success status
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully']);
        }
    }
    