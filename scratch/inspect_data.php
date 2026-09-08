<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\LeaderReport;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

echo "--- CIRCLES ---\n";
foreach (Circle::all() as $c) {
    echo "Circle ID: {$c->id} | Name: {$c->name} | City ID: {$c->city_id} | District ID: {$c->district_id}\n";
}

echo "\n--- DISTRICTS ---\n";
foreach (District::all() as $d) {
    echo "District ID: {$d->id} | Name: {$d->name} | DED: {$d->ded_user_id}\n";
}

echo "\n--- AHMEDABAD USERS ---\n";
$ahmedabadUsers = User::query()
    ->whereRaw("LOWER(city) LIKE '%ahmedabad%'")
    ->orWhereRaw("LOWER(city_of_residence) LIKE '%ahmedabad%'")
    ->get();
echo 'Count: '.$ahmedabadUsers->count()."\n";
foreach ($ahmedabadUsers as $u) {
    $memberships = CircleMember::where('user_id', $u->id)->pluck('circle_id')->all();
    echo "User ID: {$u->id} | Name: {$u->first_name} {$u->last_name} | City: {$u->city} | Memberships: ".json_encode($memberships)."\n";
}

echo "\n--- ALL USERS WITH CIRCLE MEMBERSHIPS ---\n";
$cms = CircleMember::with(['user', 'circle'])->get();
echo 'CircleMember rows: '.$cms->count()."\n";
foreach ($cms as $cm) {
    $userName = $cm->user ? ($cm->user->first_name.' '.$cm->user->last_name) : 'No user';
    $circleName = $cm->circle ? $cm->circle->name : 'No circle';
    echo "CM ID: {$cm->id} | User: {$userName} ({$cm->user_id}) | Circle: {$circleName} ({$cm->circle_id}) | Status: {$cm->status}\n";
}

echo "\n--- LEADER REPORTS ---\n";
$reports = LeaderReport::with(['circle', 'submitter'])->get();
echo 'LeaderReport rows: '.$reports->count()."\n";
foreach ($reports as $r) {
    $circleName = $r->circle ? $r->circle->name : 'No circle';
    echo "Report ID: {$r->id} | Circle: {$circleName} ({$r->circle_id}) | Type: {$r->report_type} | Period: {$r->period}\n";
}

echo "\n--- PAYMENTS ---\n";
$payments = Payment::with(['user'])->take(10)->get();
echo 'Payments rows: '.$payments->count()."\n";
foreach ($payments as $p) {
    $userName = $p->user ? ($p->user->first_name.' '.$p->user->last_name) : 'No user';
    echo "Payment ID: {$p->id} | User: {$userName} | Amount: {$p->amount} | Status: {$p->status}\n";
}
