<?php

namespace App\Mail;

use App\Models\Store\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public string $status,
        public ?string $notes = null,
        public ?string $slipUrl = null
    ) {}

    public function build(): self
    {
        $orderNo = $this->order->order_no ?: $this->order->id;
        $statusKey = strtolower($this->status);

        $subject = match ($statusKey) {
            'processing' => "Your Peers Store Order #{$orderNo} is Being Processed",
            'shipped' => "Your Peers Store Order #{$orderNo} Has Been Shipped",
            'out_for_delivery', 'ready_for_pickup' => "Your Peers Store Order #{$orderNo} is Out for Delivery!",
            'delivered' => "Your Peers Store Order #{$orderNo} Has Been Delivered",
            'cancelled' => "Your Peers Store Order #{$orderNo} Has Been Cancelled",
            default => "Update on Your Peers Store Order #{$orderNo}",
        };

        return $this->subject($subject)
            ->view('emails.store.order_status');
    }
}
