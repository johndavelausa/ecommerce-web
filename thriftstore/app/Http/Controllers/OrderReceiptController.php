<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OrderReceiptController extends Controller
{
    /**
     * Download PDF receipt for a delivered order (C2 v1.4).
     */
    public function download(Request $request, Order $order)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('customer')) {
            abort(403);
        }
        if ((int) $order->customer_id !== (int) $user->id) {
            abort(404);
        }
        if (! in_array($order->status, ['delivered', 'received', 'completed'], true)) {
            abort(404);
        }

        $order->load(['items.product', 'seller', 'customer']);

        $pdf = Pdf::loadView('customer.receipt', ['order' => $order])
            ->setPaper('a4');

        return $pdf->download('order-' . $order->id . '-receipt.pdf');
    }
}
