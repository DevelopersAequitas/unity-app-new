<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | PEARS GLOBAL — Human-Centred Communication & Experience Language Framework
    |--------------------------------------------------------------------------
    |
    | Governs the conversational and psychological orientation of the Ask &
    | Peer Support platform. Shifting from transactional utility to a
    | dignified, relationship-oriented community experience:
    |
    |  • transaction  -> relationship
    |  • request      -> contribution / requirement
    |  • user         -> person / peer
    |  • response     -> support
    |  • completion   -> appreciation
    |  • outcome      -> impact
    |
    */

    'orientation' => [
        'transaction' => 'relationship',
        'request' => 'contribution',
        'user' => 'person',
        'response' => 'support',
        'completion' => 'appreciation',
        'outcome' => 'impact',
    ],

    /*
    |--------------------------------------------------------------------------
    | Experience Language Touchpoints & Copywriting
    |--------------------------------------------------------------------------
    |
    | Recommended wording designed to ensure every interaction leaves the member
    | feeling respected, valued, capable, and honoured.
    |
    */
    'language_framework' => [
        'home_opening' => [
            'primary' => 'How can your Peers support you today?',
            'alternative' => 'What would make your journey easier today?',
            'feeling' => 'Supported & Welcomed: I am not being processed by an app, I am being received by a community that values me.',
        ],
        'flows' => [
            'collaboration' => [
                'name' => 'Find a Collaborator',
                'description' => 'Find the right peer to build and create together.',
                'action_label' => 'Find Collaborator',
                'feeling' => 'Empowered: Capable of creating meaningful initiatives together.',
            ],
            'referral' => [
                'name' => 'Help me make the right connection',
                'description' => 'Connect with the right peer for meaningful relationships and opportunities.',
                'action_label' => 'Connect with the right person',
                'feeling' => 'Dignified: I am expanding my professional circle with purpose.',
            ],
            'help' => [
                'name' => 'Seek Guidance & Support',
                'description' => 'How can your Peers support you today? Guidance, advice, and community backing.',
                'action_label' => 'Seek Guidance',
                'feeling' => 'Safe & Respected: Seeking guidance is a sign of leadership and growth.',
            ],
        ],
        'form_fields' => [
            'bring' => [
                'label' => 'What I can contribute',
                'placeholder' => 'Share your strengths, capabilities, and what you bring to the table...',
                'feeling' => 'Valued & Capable: I am a contributing partner, not just a taker.',
            ],
            'need' => [
                'label' => 'Where I could use support',
                'placeholder' => 'Describe where peer collaboration and support would accelerate your journey...',
                'feeling' => 'Legitimate & Clear: My requirement is valid and clearly articulated.',
            ],
        ],
        'actions' => [
            'submit_ask' => [
                'label' => 'Share my requirement',
                'feeling' => 'Hopeful & Connected: I am putting my vision into the hands of people who care.',
            ],
            'connect_peer' => [
                'label' => 'Connect with Peer',
                'feeling' => 'Approachable & Professional: Reaching out is easy, natural, and respected.',
            ],
            'offer_support' => [
                'label' => 'Offer my support',
                'feeling' => 'Generous & Active: My expertise is valuable and can make a real difference.',
            ],
            'close_and_appreciate' => [
                'label' => 'Close the loop & appreciate',
                'feeling' => 'Honoured & Complete: A successful cycle of collaboration is fulfilled.',
            ],
        ],
        'discovery' => [
            'matches_headline' => ':count Peers who can support your journey',
        ],
        'closure_and_outcome' => [
            'question' => 'What did this connection make possible?',
            'appreciation_prompt' => 'Recognise your Peer',
            'feeling' => 'Reflective & Fulfilled: This interaction was meaningful and created real progress.',
        ],
        'daily_pulse' => [
            'gratitude_title' => "Today's Gratitude — Recognise a peer who helped you",
            'gratitude_prompt' => 'What did this person make possible for you? Let them know their impact.',
            'feeling' => 'Inspiring & Contributive: And now I can help someone else.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Closure Outcome Statuses & Impact Reasons
    |--------------------------------------------------------------------------
    |
    | Standard outcome options presented when closing a requirement loop.
    |
    */
    'closure_outcomes' => [
        'impact_created' => [
            'code' => 'impact_created',
            'label' => 'Meaningful progress & impact created',
            'allows_recognition' => true,
        ],
        'connection_made' => [
            'code' => 'connection_made',
            'label' => 'Connected with the right peer',
            'allows_recognition' => true,
        ],
        'collaborating' => [
            'code' => 'collaborating',
            'label' => 'Actively collaborating together',
            'allows_recognition' => true,
        ],
        'guidance_received' => [
            'code' => 'guidance_received',
            'label' => 'Received helpful guidance and insights',
            'allows_recognition' => true,
        ],
        'exploring_alternatives' => [
            'code' => 'exploring_alternatives',
            'label' => 'Exploring other avenues for now',
            'allows_recognition' => false,
        ],
    ],
];
