<?php

return [

    'tz' => 'Europe/Moscow',

    'page' => [
        'size' => 40,
        'comments' => 5,
    ],

    'age' => [
        'min' => 18
    ],

    'code' => [
        'resend' => 30,
    ],

    'media' => [
        'mimes' => 'jpeg,jpg,png,mp4,mov,heic,HEIC,MOV',
        'maxsize' => 80000000, // 100 mb
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
            'allow_paid_posts_for_paid_accounts' => true,
            'caps' => [
                'subscription' => 60,
                'tip' => 1000,
                'post' => 1000,
                'message' => 1000,
                'discount' => 95,
            ]
        ],
        'payout' => [
            'min' => 1
        ],
        'currency' => [
            'symbol' => '$',
            'code' => 'USD',
            'format' => '%1$s%2$d',
        ],
        'commission' => '20',
    ],

    'profile' => [
        'creators' => [
            'verification' => [
                'require' => true
            ]
        ],
        'avatar' => [
            'maxsize' => 20000,
            'resize' => '300x300',
        ],
        'cover' => [
            'maxsize' => 20000,
            'resize' => '1920x1080',
        ],
    ],

    'screenshot' => [
        'resize' => '1280x720',
    ],
];
