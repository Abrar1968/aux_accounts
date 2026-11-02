{{-- Composer email: table layout + inline styles for Gmail / Outlook / Apple Mail. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $subject }}</title>
<style>
  @media only screen and (max-width: 480px) {
    .pad { padding-left: 16px !important; padding-right: 16px !important; }
    .outer { padding: 12px 6px !important; }
    .tx td, .tx th { padding: 6px 4px !important; font-size: 11px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#f3f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="outer" style="background:#f3f5f9;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
    <tr><td class="pad" style="background:#2b57db;padding:20px 28px;">
      <p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:.2px;">{{ $company }}</p>
      @if($website)<p style="margin:2px 0 0;font-size:12px;color:#d9e6ff;">{{ preg_replace('#^https?://#', '', $website) }}</p>@endif
    </td></tr>
    <tr><td class="pad" style="padding:28px 28px 8px;">
      <h1 style="margin:0 0 18px;font-size:19px;line-height:1.35;color:#0b1220;">{{ \Illuminate\Support\Str::beforeLast($subject, ' · ') ?: $subject }}</h1>
      @if($greeting)<p style="margin:0 0 12px;font-size:15px;line-height:1.6;">{{ $greeting }}</p>@endif
      @if($intro)<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">{!! nl2br(e($intro)) !!}</p>@endif

      @if(count($details))
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;margin:0 0 20px;">
        @foreach($details as $i => $d)
        <tr>
          <td style="padding:10px 14px;font-size:13px;color:#6b7280;width:45%;{{ $i ? 'border-top:1px solid #eef0f4;' : '' }}">{{ $d['label'] }}</td>
          <td style="padding:10px 14px;font-size:14px;font-weight:600;color:#0b1220;text-align:right;{{ $i ? 'border-top:1px solid #eef0f4;' : '' }}">{{ $d['value'] }}</td>
        </tr>
        @endforeach
      </table>
      @endif

      @if($table && count($table['rows'] ?? []))
      <table role="presentation" class="tx" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 20px;font-size:12px;">
        <tr>@foreach($table['columns'] as $c)<th style="background:#f3f5f9;padding:8px;text-align:{{ $loop->remaining < 2 ? 'right' : 'left' }};color:#6b7280;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.4px;">{{ $c }}</th>@endforeach</tr>
        @foreach($table['rows'] as $row)
        <tr>@foreach($row as $cell)<td style="padding:7px 8px;border-bottom:1px solid #eef0f4;vertical-align:top;text-align:{{ $loop->remaining < 2 ? 'right' : 'left' }};white-space:{{ $loop->remaining < 2 ? 'nowrap' : 'normal' }};word-break:break-word;">{{ $cell }}</td>@endforeach</tr>
        @endforeach
      </table>
      @endif

      @if($closing)<p style="margin:0 0 20px;font-size:15px;line-height:1.6;">{!! nl2br(e($closing)) !!}</p>@endif
      <p style="margin:0;font-size:15px;line-height:1.6;">Kind regards,<br><strong>{{ $sender }}</strong>@if($title)<br><span style="color:#6b7280;">{{ $title }}, {{ $company }}</span>@endif</p>
    </td></tr>
    <tr><td class="pad" style="padding:20px 28px 24px;">
      <p style="margin:0;border-top:1px solid #eef0f4;padding-top:14px;font-size:12px;line-height:1.6;color:#9ca3af;">
        {{ $company }}@if($address) · {{ $address }}@endif @if($phone) · {{ $phone }}@endif<br>
        Reply to this email or write to {{ $replyTo }} for any questions.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
