<?php

namespace App\Http\Controllers\Admin;

use App\Core\Models\Order;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * Download order receipt as PDF
     */
    public function downloadPdf(Order $order): Response
    {
        // Check permission
        if (!Gate::forUser(auth()->guard('admin')->user())->allows('manage_orders')) {
            abort(403);
        }

        // Load relationships
        $order->load(['store', 'customer', 'promoCode', 'paymentMethod']);

        // Ensure all data is UTF-8 encoded
        $order->items_snapshot = array_map(function ($item) {
            if (isset($item['name'])) {
                $item['name'] = mb_convert_encoding($item['name'], 'UTF-8', 'UTF-8');
            }
            if (isset($item['modifiers']) && is_array($item['modifiers'])) {
                foreach ($item['modifiers'] as &$modifier) {
                    if (isset($modifier['name'])) {
                        $modifier['name'] = mb_convert_encoding($modifier['name'], 'UTF-8', 'UTF-8');
                    }
                }
            }
            return $item;
        }, $order->items_snapshot ?? []);

        // Generate PDF
        $pdf = Pdf::loadView('orders.receipt', [
            'order' => $order,
            'store' => $order->store,
            'customer' => $order->customer,
            'htmlDir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);

        $filename = 'order-' . $order->id . '-' . $order->pickup_code . '.pdf';
        $filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');

        return $pdf->download($filename);
    }
}

