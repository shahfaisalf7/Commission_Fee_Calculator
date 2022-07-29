<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Commission Options
    |--------------------------------------------------------------------------
    |
    | The following configurations are used for calculating each payment
    | commission.
    | If you changed any of these settings please keep in mind to run:
    | php artisan optimize
    |
    */

    'deposit' => [
        'business' => [
            'commissionRate' => 0.03,
        ],
        'private' => [
            'commissionRate' => 0.03,
        ],
    ],

    'withdraw' => [
        'business' => [
            'commissionRate' => 0.5,
        ],
        'private' => [
            'commissionRate' => 0.3,
            'commissionFreeAmount' => 1000,
            'commissionFreeLimit' => 3,
        ],
    ],

    'currencyConvertionUrl' => 'https://developers.paysera.com/tasks/api/currency-exchange-rates',


];
