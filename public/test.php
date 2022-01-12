<?php
/*$ar = [
    'number' => '2585265103444954',
    'expirationYear' => '2025',
    'expirationMonth' => '12',
    'cvv' => '123',
    'cardHolder' => 'Michael Demin',
];*/

/*$ar = [
    'paymentSource' => [
        'type' => 'token',
        'value' => 'df4cb232-35c5-4a89-938d-f11c2efa53b5',
        '3ds' => false
    ],
    'sku' => [
        'title' => 'Initial Payment',
        'siteId' => '876826',
        'price' => [
            [
                'offset' => '0d',
                'amount' => 1.00,
                'currency' => 'USD',
                'repeat' => false
            ]
        ],
    ],
    'consumer' => [
        'ip' => '178.140.173.99',
        'email' => 'contact@uniprogy.com',
        'externalId' => "001",
    ]
];*/

/*$ar = [
    'amount' => 1.00,
    'reason' => 'Initial Payment Return'
];*/

/*$ar = [
    'paymentSource' => [
        'type' => 'consumer',
        'value' => '96894662',
        '3ds' => false
    ],
    'sku' => [
        'title' => 'Post Unlock',
        'siteId' => '876826',
        'price' => [
            [
                'offset' => '0d',
                'amount' => 5.00,
                'currency' => 'USD',
                'repeat' => false
            ]
        ],
    ],
    'consumer' => [
        'id' => '96894662',
        'ip' => '178.140.173.99',
    ]
];*/

$ar = [
    'paymentSource' => [
        'type' => 'consumer',
        'value' => '96894662',
        '3ds' => false
    ],
    'sku' => [
        'title' => 'Subscription to User',
        'siteId' => '876826',
        'price' => [
            [
                'offset' => '0d',
                'amount' => 5.00,
                'currency' => 'USD',
                'repeat' => false
            ],
            [
                'offset' => '1d',
                'amount' => 5.00,
                'currency' => 'USD',
                'repeat' => true
            ]
        ],
    ],
    'consumer' => [
        'id' => '96894662',
        'ip' => '178.140.173.99',
    ],
    'url' => [
        'ipnUrl' => 'https://api.bitfan.uniprogy.com/latest/log'
    ]
];

echo json_encode($ar);
