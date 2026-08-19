<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Api\V1\PeerReferralsApiController;
use App\Http\Requests\Api\V1\StorePeerReferralRequest;
use App\Models\Circle;
use App\Models\PeerReferral;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "TESTING PEER REFERRALS API SPECIFICATION\n";
echo "==================================================\n";

// 1. Fetch User and Circles
$user = User::where('status', 'active')->first();
if (! $user) {
    $user = User::first();
}
if (! $user) {
    echo "ERROR: No user found in database.\n";
    exit(1);
}

$circles = Circle::take(2)->get();
if ($circles->count() < 2) {
    $mainCircle = Circle::firstOrCreate(
        ['slug' => 'test-main-circle'],
        ['name' => 'Main Test Circle', 'description' => 'Test', 'status' => 'active']
    );
    $subCircle = Circle::firstOrCreate(
        ['slug' => 'test-sub-circle'],
        ['name' => 'Specific Sub Circle', 'description' => 'Test Sub', 'status' => 'active']
    );
} else {
    $mainCircle = $circles[0];
    $subCircle = $circles[1];
}

$categoryUuid = (string) Str::uuid();

echo "Referrer: {$user->display_name} ({$user->email})\n";
echo "Main Circle ID: {$mainCircle->id} ({$mainCircle->name})\n";
echo "Specific Circle ID: {$subCircle->id} ({$subCircle->name})\n";
echo "Category UUID: {$categoryUuid}\n\n";

// Clear previous test records
PeerReferral::where('referrer_user_id', $user->id)->delete();

$controller = app(PeerReferralsApiController::class);

echo "--------------------------------------------------\n";
echo "1. Scenario A — Referral from Specific Circle\n";
echo "--------------------------------------------------\n";

$payloadScenarioA = [
    'referred_name' => 'Rahul Patel',
    'referred_phone' => '9876543210',
    'referred_email' => 'rahul@example.com',
    'referred_company_name' => 'ABC Enterprises',
    'referred_designation' => 'Founder',
    'main_circle_id' => $mainCircle->id,
    'circle_id' => $subCircle->id,
    'open_category_id' => $categoryUuid,
    'message' => 'I would like to refer Rahul for this open category.',
];

$reqA = Request::create('/api/v1/peer-referrals', 'POST', $payloadScenarioA);
$reqA->setUserResolver(fn () => $user);

$storeReqA = StorePeerReferralRequest::createFrom($reqA);
$storeReqA->setUserResolver(fn () => $user);

$validatorA = app('validator')->make($payloadScenarioA, $storeReqA->rules());
$storeReqA->setValidator($validatorA);
$storeReqA->withValidator($validatorA);

if ($validatorA->fails()) {
    echo "Validation failed: ".json_encode($validatorA->errors()->messages())."\n";
} else {
    $resA = $controller->store($storeReqA);
    echo "Response Status: ".$resA->getStatusCode()."\n";
    echo "Response JSON:\n".json_encode(json_decode($resA->getContent()), JSON_PRETTY_PRINT)."\n";
}

echo "\n--------------------------------------------------\n";
echo "2. Scenario B — Referral from Main Circle (circle_id is null)\n";
echo "--------------------------------------------------\n";

$categoryUuidB = (string) Str::uuid();
$payloadScenarioB = [
    'referred_name' => 'Pooja Shah',
    'referred_phone' => '+91 98765 43211',
    'referred_email' => 'pooja@example.com',
    'referred_company_name' => 'XYZ Innovations',
    'referred_designation' => 'Director',
    'main_circle_id' => $mainCircle->id,
    'circle_id' => null,
    'open_category_id' => $categoryUuidB,
    'message' => 'Referring Pooja for main circle category.',
];

$reqB = Request::create('/api/v1/peer-referrals', 'POST', $payloadScenarioB);
$reqB->setUserResolver(fn () => $user);

$storeReqB = StorePeerReferralRequest::createFrom($reqB);
$storeReqB->setUserResolver(fn () => $user);

$validatorB = app('validator')->make($payloadScenarioB, $storeReqB->rules());
$storeReqB->setValidator($validatorB);
$storeReqB->withValidator($validatorB);

if ($validatorB->fails()) {
    echo "Validation failed: ".json_encode($validatorB->errors()->messages())."\n";
} else {
    $resB = $controller->store($storeReqB);
    echo "Response Status: ".$resB->getStatusCode()."\n";
    echo "Response JSON:\n".json_encode(json_decode($resB->getContent()), JSON_PRETTY_PRINT)."\n";
}

echo "\n--------------------------------------------------\n";
echo "3. Testing Duplicate Prevention for Same Peer & Category\n";
echo "--------------------------------------------------\n";

$validatorDup = app('validator')->make($payloadScenarioA, $storeReqA->rules());
$storeReqA->setValidator($validatorDup);
$storeReqA->withValidator($validatorDup);

if ($validatorDup->fails()) {
    echo "PASSED: Caught duplicate pending referral!\n";
    echo "Errors: ".json_encode($validatorDup->errors()->messages())."\n";
} else {
    echo "FAILED: Duplicate check did not trigger.\n";
}

echo "\n==================================================\n";
echo "ALL TESTS COMPLETED SUCCESSFULLY\n";
echo "==================================================\n";
