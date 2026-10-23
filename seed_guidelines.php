use App\Models\ImpactGuideline;

\ = [
    [
        'id' => '2547ab74-ca7a-4822-99b2-2a3331ce681a',
        'action' => 'Closed a business deal through Peers Global',
        'category' => 'Business & Growth',
        'impact_value' => 5,
        'impact_unit' => 'Lives',
        'display_order' => 1,
        'is_active' => 1,
    ],
    [
        'id' => '0b85a01f-edd8-4045-a773-3972a8ae61cd',
        'action' => 'Gave a qualified business referral',
        'category' => 'Business & Growth',
        'impact_value' => 1,
        'impact_unit' => 'Life',
        'display_order' => 2,
        'is_active' => 1,
    ],
    [
        'id' => 'ddea3581-06eb-4ac1-81fb-d1ad5e9a4519',
        'action' => 'Created a collaboration opportunity',
        'category' => 'Business & Growth',
        'impact_value' => 1,
        'impact_unit' => 'Life',
        'display_order' => 3,
        'is_active' => 1,
    ],
    [
        'id' => '42dc50b3-7eaa-4e4b-9e84-e73468a320e8',
        'action' => 'Connected two members for collaboration',
        'category' => 'Business & Growth',
        'impact_value' => 1,
        'impact_unit' => 'Life',
        'display_order' => 4,
        'is_active' => 1,
    ],
    [
        'id' => '71753fc0-47b1-481c-b3c1-a2a46c23b08a',
        'action' => 'Received a testimonial or review from a peer',
        'category' => 'Trust & Visibility',
        'impact_value' => 5,
        'impact_unit' => 'Lives',
        'display_order' => 5,
        'is_active' => 1,
    ],
];

foreach (\ as \) {
    ImpactGuideline::updateOrCreate(['id' => \['id']], \);
}
echo 'Count: ' . ImpactGuideline::count();
