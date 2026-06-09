@component('mail::message')
# Coin Claim Received

Hello {{ $userName }},

Thank you for submitting your coin claim. We have received your request and it is currently pending review by the Peers Global Unity team.

## Claim Details

| Detail | Information |
| :--- | :--- |
| Claim ID | {{ $claimId ?? '-' }} |
| Activity | {{ $activityName ?? '-' }} |
| Coins Claimed | {{ $coinsClaimed ?? '-' }} |
| Submitted Date | {{ $submittedDate ?? '-' }} |
| Status | {{ $status ?? 'Pending Review' }} |

You will be notified once your claim has been reviewed.

Thank you,<br>
Peers Global Unity
@endcomponent
