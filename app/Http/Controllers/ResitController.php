<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

/**
 * Muat turun resit pembayaran sebagai PDF melalui URL yang ditandatangani
 * (digunakan mobile/API supaya boleh dibuka dalam pelayar tanpa header
 * Authorization). Pengesahan dibuat oleh middleware `signed` pada route.
 */
class ResitController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function show(Request $request, Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        if ($payment->status !== 'successful') {
            abort(404);
        }

        $payment->loadMissing('user.organization', 'organization');

        return $this->receipts->pdf($payment)->download($this->receipts->filename($payment));
    }
}
