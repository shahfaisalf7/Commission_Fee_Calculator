<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PaymentController;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Http\UploadedFile;

class PaymentCommissionCalculatorTest extends TestCase
{
    /**
     * Example of a basic feature test.
     *
     * @return void
     */
    public function test_payment_commissions_calculated_successfully()
    {
        $request = $this->createStorePaymentRequest(public_path('test.csv'));
        $response = (new PaymentController)->getPaymentCommissions($request, true);
        $expectedResponse = [0.6,3,0,0.06,1.5,0,0.7,0.3,0.3,3,0,0,8612];
        $this->assertTrue($response == $expectedResponse);
    }

    private function createStorePaymentRequest($filePath)
    {
        $file = new UploadedFile($filePath, 'file');
        return new StorePaymentRequest([
            'file' => $file
        ]);
    }
}
