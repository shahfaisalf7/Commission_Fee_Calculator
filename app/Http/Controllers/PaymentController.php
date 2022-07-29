<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Controllers\file\FileController;
use App\Http\Controllers\file\CsvController;
use Illuminate\Http\Request;
class PaymentController extends Controller
{
    /**
     * Displays the landing page of the application.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('payment');
    }

    /**
     * Receives a payment list from a CSV file.
     *
     * @param  \Illuminate\Http\StorePaymentRequest  $request
     * @return redirect to payments page
     */
    public function storeData(StorePaymentRequest $request)
    {
        $commissions = $this->getPaymentCommissions($request);
        return redirect()->back()->with('commissions', $commissions);
    }
    /**
     * Commission calculation starts from this method.
     * PaymentCommissionCalculatorTest calls this method
     *
     * @param  \Illuminate\Http\StorePaymentRequest  $request
     * @param  Boolean  $test
     * @return Array $commissions
     */
    public function getPaymentCommissions ($request, $test=false)
         {
             $path = "payment/";
             $fileUploaded = FileController::uploadFile($request['file'], $path);
             $csvData = (new CsvController)->read($fileUploaded);
             $payments = (new CommissionController($test))->calculateCommission($csvData, $test);
             $commissions = [];
             foreach ($payments as $payment)
             {
                 array_push($commissions, $payment['commissionRate']);
             }
             FileController::deleteFile($fileUploaded);
             return $commissions;
         }


}
