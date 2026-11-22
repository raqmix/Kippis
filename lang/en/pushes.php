<?php

// Customer push texts (brief section E). Placeholders use :name.
// Every push is sent in English and Arabic together (customers have no stored language).
return [
    'gift_expiring' => [
        'title' => 'Your gift is expiring',
        'body' => 'Your gift expires in 3 days. Don\'t miss it!',
    ],
    'points_expiring' => [
        'title' => 'Your points are expiring',
        'body' => 'Your points expire soon. Swap them for a gift!',
    ],
    'enough_points_for_gift' => [
        'title' => 'You can get a gift',
        'body' => 'You have enough points for a :gift. Swap them in the app.',
    ],
    'close_to_gift' => [
        'title' => 'Almost there',
        'body' => 'You\'re :n points away from a :gift!',
    ],
    'birthday' => [
        'title' => 'Happy birthday!',
        'body' => 'Happy birthday! Your drink is on us this week.',
    ],
    'cup_card_stamped' => [
        'title' => 'Cup card',
        'body' => 'Your next coffee is :percent% off',
        'body_free' => 'Your next coffee is free!',
    ],
    'cup_card_completed' => [
        'title' => 'Cup card',
        'body' => 'Card complete! A new card starts with your next coffee.',
    ],
    'streak_almost' => [
        'title' => 'Keep your streak going',
        'body' => ':n days in a row! Order tomorrow for a free coffee.',
    ],
    'streak_reward' => [
        'title' => 'Streak complete',
        'body' => 'Your free coffee is in My Rewards!',
    ],
    'big_win_entry' => [
        'title' => 'Monthly Big Win',
        'body' => 'You\'re in this month\'s draw!',
    ],
    'referral_reward' => [
        'title' => 'Your friend just ordered',
        'body' => 'Your friend just ordered. You both got a free coffee!',
    ],

    // Order status pushes (same meaning as before; title and body are joined EN + AR).
    'order_status' => [
        'mixing' => [
            'title' => 'Your order is being prepared',
            'body' => 'We\'re mixing it now — hang tight!',
        ],
        'ready' => [
            'title' => 'Your order is ready!',
            'body' => 'Head to the counter to pick it up.',
        ],
        'completed' => [
            'title' => 'Enjoy your Kippis!',
            'body' => 'Tap to rate your order and earn bonus points.',
        ],
        'cancelled' => [
            'title' => 'Order cancelled',
            'body' => 'Your order was cancelled. Tap for details.',
        ],
    ],
];
