<?php
/*$ar = [
    'number' => '2585265103444954',
    'expirationYear' => '2025',
    'expirationMonth' => '12',
    'cvv' => '123',
    'cardHolder' => 'Michael Demin',
];*/

$ar = [
    'paymentSource' => [
        'type' => 'token',
        'value' => 'd4d3fe07-6db4-46e2-a8c5-d444ea1c418a',
        '3ds' => false
    ],
    'sku' => [
        'title' => 'Initial Payment',
        'siteId' => '876826',
        'price' => [
            [
                'offset' => '0d',
                'amount' => 1,
                'currency' => 'USD',
                'repeat' => false
            ]
        ],
    ],
    'consumer' => [
        'ip' => '178.140.173.99'
    ]
];

echo json_encode($ar);
