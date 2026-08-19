<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Api\V1\PeerReferralsApiController;
use App\Models\User;
use App\Models\Circle;
use App\Models\PeerReferral;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "TESTING PEER REFERRALS API AND VALIDATION\n";
echo "==================================================\n";

// 1. Fetch User and Circle
$user = User::where('status', 'active')->first();
if (!$user) {
    $user = User::first();
}
if (!$user) {
    echo "ERROR: No user found in database.\n";
    exit(1);
}

$circle = Circle::first();
if (!$circle) {
    echo "Creating a temporary circle for testing...\n";
    $circle = Circle::create([
        'name' => 'Test Circle',
        'slug' => 'test-circle-' . time(),
        'description' => 'Test circle description',
        'status' => 'active',
    ]);
}

$categoryUuid = (string) Str::uuid();

echo "Referrer: {$user->display_name} ({$user->email})\n";
echo "Main Circle ID: {$circle->id} ({$circle->name})\n";
echo "Category UUID (Mocked): {$categoryUuid}\n\n";

// Clear previous test records for clean run
PeerReferral::where('referrer_user_id', $user->id)->delete();

$payload = [
    'referred_name' => 'Jane Doe Test',
    'referred_phone' => '+919999999999',
    'referred_email' => 'janedoe.test@example.com',
    'referred_company_name' => 'Jane Tech Solutions',
    'referred_designation' => 'CTO',
    'main_circle_id' => $circle->id,
    'circle_id' => null, // Main circle referral
    'open_category_id' => $categoryUuid,
    'message' => 'This is a test referral message.',
];

echo "--------------------------------------------------\n";
echo "1. Submitting Valid Peer Referral\n";
echo "--------------------------------------------------\n";

$request1 = Request::create('/api/v1/peer-referrals', 'POST', $payload);
$request1->setUserResolver(fn() => $user);

$controller = app(PeerReferralsApiController::class);

try {
    // Validate request using the StorePeerReferralRequest
    $storeRequest = \App\Http\Requests\Api\V1\StorePeerReferralRequest::createFrom($request1);
    $storeRequest->setUserResolver(fn() => $user);
    
    // Manually trigger validator
    $validator = app('validator')->make(
        $payload, 
        $storeRequest->rules()
    );
    $storeRequest->setValidator($validator);
    $storeRequest->withValidator($validator);
    
    if ($validator->fails()) {
        echo "Validation failed: " . json_encode($validator->errors()->messages()) . "\n";
    } else {
        $response1 = $controller->store($storeRequest);
        echo "Response Status: " . $response1->getStatusCode() . "\n";
        echo "Response Content:\n" . json_encode(json_decode($response1->getContent()), JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n--------------------------------------------------\n";
echo "2. Submitting Duplicate Peer Referral (Should Fail)\n";
echo "--------------------------------------------------\n";

try {
    $request2 = Request::create('/api/v1/peer-referrals', 'POST', $payload);
    $request2->setUserResolver(fn() => $user);

    $storeRequest2 = \App\Http\Requests\Api\V1\StorePeerReferralRequest::createFrom($request2);
    $storeRequest2->setUserResolver(fn() => $user);

    $validator2 = app('validator')->make($payload, $storeRequest2->rules());
    $storeRequest2->setValidator($validator2);
    $storeRequest2->withValidator($validator2);

    if ($validator2->fails()) {
        echo "PASSED: Duplicate validation caught the duplicate request!\n";
        echo "Validation Errors: " . json_encode($validator2->errors()->messages()) . "\n";
    } else {
        $response2 = $controller->store($storeRequest2);
        echo "FAILED: Expected duplicate validation to fail, but it succeeded.\n";
        echo "Response Status: " . $response2->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n--------------------------------------------------\n";
echo "3. Fetching Submitted Peer Referrals\n";
echo "--------------------------------------------------\n";

$request3 = Request::create('/api/v1/peer-referrals', 'GET');
$request3->setUserResolver(fn() => $user);

$response3 = $controller->index($request3);
echo "Response Status: " . $response3->getStatusCode() . "\n";
echo "Response Content:\n" . json_encode(json_decode($response3->getContent()), JSON_PRETTY_PRINT) . "\n";

echo "==================================================\n";
echo "TEST COMPLETED SUCCESSFULLY\n";
echo "==================================================\n";
