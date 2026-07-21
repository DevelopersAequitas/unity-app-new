@extends('emails.layouts.email')

@section('title', 'Your Event QR Code Pass')

@section('content')
    <h2 style="color: #ffffff; margin-top: 0; margin-bottom: 16px; font-size: 20px;">
        Hello {{ $visitorName }},
    </h2>

    <p style="margin-bottom: 20px;">
        Thank you for registering for <strong>{{ $eventTitle }}</strong>. Here is your official entry pass containing your QR code.
    </p>

    <div style="background-color: #1a2234; border: 1px solid #2d3748; border-radius: 8px; padding: 20px; margin-bottom: 24px; text-align: center;">
        <h3 style="color: #6366f1; margin-top: 0; margin-bottom: 12px; font-size: 18px;">
            {{ $eventTitle }}
        </h3>

        <p style="margin: 4px 0; color: #cbd5e1; font-size: 14px;">
            📅 <strong>Date:</strong> {{ $eventDate }}
        </p>
        <p style="margin: 4px 0; color: #cbd5e1; font-size: 14px;">
            ⏰ <strong>Time:</strong> {{ $eventTime }}
        </p>
        @if($eventLocation)
            <p style="margin: 4px 0; color: #cbd5e1; font-size: 14px;">
                📍 <strong>Location:</strong> {{ $eventLocation }}
            </p>
        @endif

        <p style="margin-top: 16px; margin-bottom: 0; font-size: 14px; color: #a5b4fc; font-weight: 600;">
            📲 Your official QR Code Entry Pass is attached below.
        </p>
        <p style="margin-top: 4px; margin-bottom: 0; font-size: 13px; color: #94a3b8;">
            Please open the attached QR pass image at the check-in counter for instant entry.
        </p>
    </div>

    <p style="margin-bottom: 0; font-size: 14px; color: #cbd5e1;">
        We look forward to hosting you!
    </p>
@endsection

@section('footer')
    <p style="margin: 0;">Greenpreneur Event Operations Team</p>
    <p style="margin: 4px 0 0 0; color: #a5b4fc; font-size: 12px;">Please bring this QR pass in digital or printed format for entry.</p>
@endsection
