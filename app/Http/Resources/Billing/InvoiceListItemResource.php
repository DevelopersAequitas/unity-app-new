<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this['invoice_id'] ?? null,
            'invoice_number' => $this['invoice_number'] ?? null,
            'date' => $this['date'] ?? null,
            'due_date' => $this['due_date'] ?? null,
            'status' => $this['status'] ?? null,
            'payment_status' => $this['payment_status'] ?? ($this['status'] ?? null),
            'currency_code' => $this['currency_code'] ?? null,
            'total' => $this['total'] ?? null,
            'balance' => $this['balance'] ?? null,
            'customer_name' => $this['customer_name'] ?? null,
            'subscription_id' => $this['subscription_id'] ?? null,
            'invoice_url' => $this['invoice_url'] ?? null,
            'pdf_url' => $this['pdf_url'] ?? null,
            'created_time' => $this['created_time'] ?? null,

            // Unified payment & Circle Package invoice fields
            'payment_reference' => $this['payment_reference'] ?? ($this['reference_number'] ?? null),
            'payment_id' => $this['payment_id'] ?? null,
            'order_id' => $this['order_id'] ?? null,
            'payment_type' => $this['payment_type'] ?? 'membership',
            'package_name' => $this['package_name'] ?? null,
            'circle_name' => $this['circle_name'] ?? null,
            'circle_id' => $this['circle_id'] ?? null,
            'base_amount' => $this['base_amount'] ?? null,
            'gst_percent' => $this['gst_percent'] ?? null,
            'gst_amount' => $this['gst_amount'] ?? null,
            'total_amount' => $this['total_amount'] ?? ($this['total'] ?? null),
            'payment_status' => $this['payment_status'] ?? ($this['status'] ?? 'paid'),
            'payment_date' => $this['payment_date'] ?? ($this['date'] ?? null),
            'user_id' => $this['user_id'] ?? null,
        ];
    }
}
