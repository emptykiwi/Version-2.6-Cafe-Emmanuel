<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;

    // Minimum amount for Paymongo is usually 100 PHP
    if ($amount < 100) {
        die("Amount must be at least PHP 100.00");
    }

    // Your Paymongo Secret Key
    $secret_key = 'sk_test_4wnAfmzuwJANdZP9sB8Zxf1o';

    // Dynamically get the base URL for redirects (success/cancel)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; 
    $path = dirname($_SERVER['PHP_SELF']); 
    $base_url = $protocol . "://" . $host . $path;

    // Prepare the payload for Paymongo Checkout Session
    $data = [
        "data" => [
            "attributes" => [
                "line_items" => [[
                    "currency" => "PHP",
                    "amount" => intval($amount * 100), // Convert to centavos (e.g., 100 becomes 10000)
                    "name" => "Cafe Emmanuel Order",
                    "quantity" => 1
                ]],
                "payment_method_types" => ["gcash", "paymaya"], // You can add "paymaya", "card" etc. here
                "description" => "Online Order from Cafe Emmanuel",
                "success_url" => $base_url . "/success.html", // Redirect to success.html upon payment
                "cancel_url" => $base_url . "/product.php"    // Redirect back to menu if failed/canceled
            ]
        ]
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ":");
    
    // ==========================================
    // 🚨 CRITICAL FIX: BYPASS SSL FOR LOCALHOST
    // ==========================================
    // This prevents cURL from failing on XAMPP/WAMP due to missing SSL certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    // Handle the response
    if ($err) {
        die("cURL Error #:" . $err); // Will show if your local server blocks the request completely
    } else {
        $result = json_decode($response, true);
        
        // If the checkout URL is successfully generated, redirect the user to Paymongo
        if (isset($result['data']['attributes']['checkout_url'])) {
            header("Location: " . $result['data']['attributes']['checkout_url']);
            exit;
        } else {
            // If it fails, print the exact error Paymongo returned so you can debug it
            echo "<h3>Paymongo API Error:</h3>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
            die();
        }
    }
} else {
    die("Invalid request method.");
}
?>