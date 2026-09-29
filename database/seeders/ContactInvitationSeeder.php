<?php

namespace Database\Seeders;

use App\Models\ContactInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactInvitationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(3)->get();
        if ($users->count() === 0) {
            $this->command->warn('No users found! Please seed users first.');

            return;
        }

        $sampleContacts = [
            ['name' => 'Ramesh Patel',   'phone' => '+919876543210', 'email' => 'ramesh@example.com', 'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Suresh Shah',    'phone' => '+919812345678', 'email' => null,                  'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Priya Mehta',    'phone' => '+919988776655', 'email' => 'priya@test.com',      'status' => 'failed',  'wa_status' => 'not_completed'],
            ['name' => 'Ankit Joshi',    'phone' => '+919765432109', 'email' => null,                  'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Kavya Desai',    'phone' => '+919845612378', 'email' => 'kavya@demo.com',      'status' => 'pending', 'wa_status' => 'not_completed'],
            ['name' => 'Mohan Trivedi',  'phone' => '+919632147850', 'email' => null,                  'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Nisha Pandya',   'phone' => '+919874561230', 'email' => 'nisha@sample.com',    'status' => 'failed',  'wa_status' => 'not_completed'],
            ['name' => 'Raj Sharma',     'phone' => '+919900112233', 'email' => null,                  'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Deepa Kapoor',   'phone' => '+919977554433', 'email' => 'deepa@test.com',      'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Vijay Kumar',    'phone' => '+919811223344', 'email' => null,                  'status' => 'pending', 'wa_status' => 'not_completed'],
            ['name' => 'Geeta Bhatt',    'phone' => '+919898001122', 'email' => 'geeta@mail.com',      'status' => 'sent',    'wa_status' => 'completed'],
            ['name' => 'Hiren Solanki',  'phone' => '+919773334455', 'email' => null,                  'status' => 'sent',    'wa_status' => 'completed'],
        ];

        $count = 0;
        foreach ($sampleContacts as $i => $c) {
            $user = $users[$i % $users->count()];
            ContactInvitation::create([
                'user_id' => $user->id,
                'contact_name' => $c['name'],
                'contact_phone' => $c['phone'],
                'contact_email' => $c['email'],
                'mobile_normalized' => preg_replace('/\D/', '', $c['phone']),
                'status' => $c['status'],
                'whatsapp_status' => $c['wa_status'],
                'whatsapp_sent_at' => $c['wa_status'] === 'completed' ? now()->subMinutes(rand(5, 2880)) : null,
                'invitation_message' => 'Hello '.$c['name'].'! I am using Unity App — a platform to connect with like-minded people. Join me using my referral link and get bonus coins!',
                'error_message' => $c['wa_status'] === 'not_completed' ? 'WhatsApp delivery failed: Number not registered on WhatsApp.' : null,
            ]);
            $count++;
        }

        $this->command->info("✅ Inserted {$count} contact invitations successfully!");
    }
}
