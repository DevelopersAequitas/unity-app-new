<?php

namespace App\Services\Store;

use App\Models\Store\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OrderSlipService
{
    /**
     * Generate packing slip PDF and save to disk, returning public URL.
     */
    public function generateAndSaveSlipPdf(Order $order): ?string
    {
        try {
            $order->loadMissing(['user', 'items.product', 'items.variant', 'payments.ledgerTransaction']);

            $orderNo = $order->order_no ?: $order->id;
            $fileName = 'slip_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderNo) . '.pdf';
            $storageDir = storage_path('app/public/slips');

            if (! File::exists($storageDir)) {
                File::makeDirectory($storageDir, 0755, true);
            }

            $filePath = $storageDir . DIRECTORY_SEPARATOR . $fileName;

            // Render view to HTML
            $html = view('admin.store.orders.packing-slip-pdf', [
                'order' => $order,
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            file_put_contents($filePath, $dompdf->output());

            $slipUrl = asset('storage/slips/' . $fileName);
            $order->update(['slip_url' => $slipUrl]);

            return $slipUrl;
        } catch (\Throwable $e) {
            Log::error('Failed to generate order slip PDF', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get or generate PDF slip URL for order.
     */
    public function getOrGenerateSlipUrl(Order $order): string
    {
        if ($order->slip_url) {
            return $order->slip_url;
        }

        $generated = $this->generateAndSaveSlipPdf($order);
        if ($generated) {
            return $generated;
        }

        return route('admin.store.orders.packing-slip', $order->id);
    }
}
