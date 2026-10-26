An Important Update Regarding Your SHOPPICK Seller Application

Seller Application Review Update

Dear {{ $sellerName }},

Thank you for your interest in becoming a Seller on SHOPPICK and for the time and effort you dedicated to your application.

After carefully reviewing your application for {{ $shopName }}, @if($decision === 'needs_resubmission')we are unable to approve it at this time because certain requirements still need to be addressed.@elsewe regret that we are unable to approve this application.@endif

Application Status: {{ $decision === 'needs_resubmission' ? 'Needs Resubmission' : 'Rejected' }}
Reason: {{ $reason }}

@if($decision === 'needs_resubmission')
We understand this may be disappointing, and we appreciate your patience and understanding. This is not a final rejection. Please address the identified requirements and resubmit your application for further review.

We value your interest in joining the SHOPPICK seller community and look forward to reviewing your updated application.

Review and Resubmit:
{{ $resubmitUrl }}
@else
We sincerely appreciate your interest and effort. This decision does not affect your Buyer account, which remains available to you.
@endif

Thank you for your understanding and continued interest in SHOPPICK.

Sincerely,
SHOPPICK Seller Support Team
