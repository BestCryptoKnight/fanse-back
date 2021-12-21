<?php

use App\PointHistory;
use App\Program;
use App\Subscription;

return [

    'tz' => 'Europe/Moscow',

    'page' => [
        'size' => 20,
        'comments' => 5,
    ],

    'age' => [
        'min' => 18
    ],

    'code' => [
        'resend' => 30,
    ],

    'media' => [
        'mimes' => 'jpg,png,heic,mp4,avi,mov,m4a,ogg',
        'maxsize' => 3000000, // 3 gb
    ],

    'post' => [
        'media' => [
            'max' => 20,
        ],
        'poll' => [
            'max' => 10
        ],
        'expire' => [
            'max' => 30
        ]
    ],

    'payment' => [
        'pricing' => [
            'allow_paid_posts_for_paid_accounts' => false,
            'caps' => [
                'subscription' => 50,
                'tip' => 100,
                'post' => 100,
                'message' => 100,
                'discount' => 50,
            ]
        ],
        'payout' => [
            'min' => 100
        ],
        'currency' => [
            'symbol' => '$',
            'code' => 'USD',
            'format' => '%1$s%2$d',
        ],
    ],

    'profile' => [
        'creators' => [
            'verification' => [
                'require' => true
            ]
        ],
        'avatar' => [
            'maxsize' => '20000',
            'resize' => '1080x1080',
        ],
        'cover' => [
            'maxsize' => '20000',
            'resize' => '1920x1080',
        ],
    ],

    'screenshot' => [
        'resize' => '1280x720',
    ],
];
