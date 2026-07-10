<p>Hello {{ $displayName }},</p>

<p>Congratulations!</p>

<p>Your request to join {{ $circleName }} has been approved by both the CD and ID teams.</p>

<p>Joining details:</p>
<ul>
    <li><strong>Circle:</strong> {{ $circleName }}</li>
    <li><strong>Category:</strong> {{ $categoryName }}</li>
    <li><strong>Request ID:</strong> {{ $joinRequestId }}</li>
    <li><strong>Approval status:</strong> Approved</li>
    <li><strong>Payment status:</strong> Unpaid</li>
    <li><strong>Amount:</strong> {{ $formattedAmount }}</li>
</ul>

@if(!empty($paymentUrl))
<p>Please complete your circle joining payment using the button below:</p>

<p>
    <a href="{{ $paymentUrl }}" style="display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Pay Now</a>
</p>

<p>Or copy and paste this link in your browser:<br>
<a href="{{ $paymentUrl }}">{{ $paymentUrl }}</a></p>
@else
<p>Your payment link is currently being generated. You can complete the payment in the mobile app once it becomes available.</p>
@endif

<p>After successful payment, your membership status will be updated according to the existing workflow.</p>

<p>Regards,<br>PeersGlobal Team</p>
