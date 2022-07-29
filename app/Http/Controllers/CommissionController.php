<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CommissionController extends Controller
{
    public function __construct($test)
    {
        // test parameter detection if the class is used in an automation test
        $this->test = $test;
        $this->config = config('paymentConfiguration');
        $this->weeklyWithdrawAmount = 0;
        $this->weeklyWithdrawCount = 0;
        $this->currencyRates = json_decode(file_get_contents(config('paymentConfiguration.currencyConvertionUrl')), true)['rates'];
    }

    /**
     * This is the Main method in CommissionController class to calculate commissions
     *
     * @param  Array  $csvData
     * @param  Boolean  $test
     * @return Array $csvData containing commissions in each record
     */
    public function calculateCommission ($csvData)
    {
        $data = $this->labelCsvData($csvData);
        $csvDataRequest = new Request($data);
        $this->validateCsvData($csvDataRequest);
        $groupedData = $this->groupDataByUserId($data);
        foreach ($groupedData as $userPayments)
        {
            $data = $this->calculateUserCommissions($data, $userPayments);
        }
        return $data;
    }

    /**
     * Assigning keys to csv data instead of numbers.
     *
     * @param  Array  $csvData
     * @return Array
     */
    public function labelCsvData ($csvData)
    {
        // $i = -1;
        return collect($csvData)
            ->map(function($row, $i=0){
                return [
                    'key' => $i,
                    'date' => $row[0],
                    'userId' => $row[1],
                    'userType' => $row[2],
                    'operationType' => $row[3],
                    'amount' => $row[4],
                    'currency' => $row[5],
                ];
                $i++;
            })
            ->toArray();
    }

    /**
     *  Data validation in CSV file.
     *
     * @param  \Illuminate\Http\Request  $csvDataRequest
     * @return Error or nothing
     */
    public function validateCsvData (Request $csvDataRequest)
    {
        $currencyRates = $this->currencyRates;
        $csvDataRequest->validate(
            [
                '*.date' => [
                    'required',
                    'date'
                ],
                '*.userId' => [
                    'required',
                    'int'
                ],
                '*.userType' => [
                    'required',
                    'in:private,business'
                ],
                '*.operationType' => [
                    'required',
                    'in:deposit,withdraw'
                ],
                '*.amount' => [
                    'required',
                    'numeric',
                    'min:1'
                ],
                '*.currency' => [
                    'required',
                    // This function checks the currency is supported and is available in the list based on currencyRateSourceUrl
                    function ($attribute, $value, $fail) use ($currencyRates) {
                        if (!isset($currencyRates[$value]))
                            $fail("The $value currency is not supported.");
                    },
                ],
            ]
        );
    }

    /**
     * group data by user id to check each user payments individually
     *
     * @param  Array  $labeledData
     * @return Array
     */
    public function groupDataByUserId ($data)
    {
        return collect($data)
            ->groupBy('userId')
            ->toArray();
    }

    /**
     * This method is to calculates each user's payment commissions individually and puts the commission in the main array
     *
     * @param  Array  $data => main array
     * @param  Array  $userPayments => a user payments
     * @return Array  $data => updated main array
     */
    public function calculateUserCommissions ($data, $userPayments)
    {
        // Sort user payments by date in ascending order
        $userPayments = $this->sortUserPaymentsByDate($userPayments);

        // detect start and end of the week based on first payment of the user
        $week = $this->defineWeek($userPayments[0]['date']);

        $weeklyWithdrawAmount = 0;
        $weeklyWithdraws = 0;
        $commissionFreeAmountDeducted = false;
        $commissionFreeAmount = $this->config['withdraw']['private']['commissionFreeAmount'];
        $commissionFreeLimit = $this->config['withdraw']['private']['commissionFreeLimit'];

        // loop through each payment and calculate commission
        foreach ($userPayments as $payment) {
            $CommissionRate = $this->config[$payment['operationType']][$payment['userType']]['commissionRate'];
            $amount = $payment['amount'];

            switch ($payment['operationType']) {
                case "deposit":
                    $data[$payment['key']]['commissionRate'] = ($amount / 100) * $CommissionRate;
                    break;
                case "withdraw":
                    switch ($payment['userType']) {
                        case "business":
                            $data[$payment['key']]['commissionRate'] = ($amount / 100) * $CommissionRate;
                            break;
                        case "private":
                            $date = Carbon::createFromDate($payment['date'])->format('Y-m-d');
                            $currencyToEuro = $this->convertCurrencyToEuro($amount, $payment['currency']);

                            // check if transaction is completed in the current week or not
                            if (($date >= $week['start']) && ($date <= $week['end'])) {
                                $weeklyWithdraws++;
                                $weeklyWithdrawAmount += $currencyToEuro;
                            } else {
                                $weeklyWithdrawAmount = $currencyToEuro;
                                $weeklyWithdraws = 1;
                                $week = $this->defineWeek($payment['date']);
                            }

                            // check if weeklyWithdrawAmount and weeklyWithdraws are not met yet
                            if ($weeklyWithdrawAmount <= $commissionFreeAmount && $weeklyWithdraws <= $commissionFreeLimit) {
                                $CommissionRate = 0;
                            } elseif ($weeklyWithdraws <= $commissionFreeLimit && $commissionFreeAmountDeducted == false) {
                                // The first attempt on commissionFreeAmount is deducted from the weeklyWithdrawAmount
                                $commissionFreeAmountDeducted = true;
                                $amount = $weeklyWithdrawAmount - $commissionFreeAmount;
                                $amount = $this->convertEuroToCurrency($amount, $payment['currency']);
                            }

                            // detect decimal places
                            $decimalPlaces = strlen(substr(strrchr($payment['amount'], "."), 1));
                            // round up commissions and update main data array
                            $data[$payment['key']]['commissionRate'] = $this->roundUp(($amount / 100) * $CommissionRate, $decimalPlaces-1);
                            break;
                    }
                    break;
            }
        }
        return $data;
    }

    /**
     * This method sorts user payments in ascending order in case the csv records are not sorted accordingly
     * This method is to check payments occured in one week or not
     * @param  Array  $userPayments
     * @return Array  $userPayments => sorted user payments ascending
     */
    public function sortUserPaymentsByDate ($userPayments)
    {
        usort($userPayments, function($a, $b) {
            return Carbon::createFromDate($a['date']) <=> Carbon::createFromDate($b['date']);
        });
        return $userPayments;
    }

    /**
     * Define start and end date of the week.
     *
     * @param  Date  $date
     * @return Array ['start' => $startDate, 'end' => $endDate]
     */
    public function defineWeek ($date)
    {
        $week= [];
        $startDate = Carbon::createFromDate($date);
        $week['start'] = $startDate->startOfWeek()->toDateString();
        $week['end'] = $startDate->endOfWeek()->toDateString();
        return $week;
    }

    /**
     * Convert the currency to Euro.
     *
     * @param  Float  $amount
     * @param  String  $currency
     * @return Float
     */
    public function convertCurrencyToEuro ($amount, $currency)
    {
        if ($currency === 'EUR')
            return $amount;
        return $amount / $this->getCurrencyRate($currency);
    }

    /**
     * Convert Euro to the provided Currency.
     *
     * @param  Float  $amount
     * @param  String  $currency
     * @return Float
     */
    public function convertEuroToCurrency ($amount, $currency)
    {
        if ($currency === 'EUR')
            return $amount;
        return $amount * $this->getCurrencyRate($currency);
    }

    /**
     * Get currency rate based on provided URL.
     * if test is running defined values for certain currencies are returned.
     *
     * @param  String  $currency
     * @return Float
     */
    public function getCurrencyRate ($currency)
    {
        if ($this->test)
            switch ($currency) {
                case "JPY":
                    return "129.53";
                case "USD":
                    return "1.1497";
            }
        return $this->currencyRates[$currency];
    }

    /**
     * Round the commission to currency's decimal places.
     *
     * @param  Float  $number
     * @param  Int  $decimalPlaces
     * @return Float
     */
    public function roundUp($number, $decimalPlaces)
    {
        if ($decimalPlaces <= 0)
            return ceil($number);
        return round($number, $decimalPlaces);
    }
}
