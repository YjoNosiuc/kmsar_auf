<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMSAR Notification</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; background:#F1F5F9; padding:24px; }
        .wrapper { max-width:600px; margin:0 auto; }
        .header { background:#1E3A8A; padding:20px 28px; border-radius:12px 12px 0 0; }
        .header-logo { color:#D4AF37; font-size:22px; font-weight:800; letter-spacing:3px; }
        .header-sub { color:rgba(255,255,255,0.75); font-size:11px; margin-top:3px; }
        .header-bar { height:3px; background:#D4AF37; margin-top:14px; border-radius:2px; }
        .body { background:#ffffff; padding:32px; }
        .greeting { font-size:17px; font-weight:700; color:#0F172A; margin-bottom:12px; }
        .message { font-size:14px; color:#475569; line-height:1.7; margin-bottom:16px; }
        .research-card { background:#F8FAFC; border:1px solid #E2E8F0; border-left:4px solid #1E3A8A; border-radius:8px; padding:16px; margin:16px 0; }
        .ref { font-size:10px; font-weight:700; color:#D4AF37; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:4px; }
        .rtitle { font-size:15px; font-weight:700; color:#1E3A8A; margin-bottom:6px; line-height:1.4; }
        .rmeta { font-size:12px; color:#64748B; }
        .rmeta span { margin-right:16px; }
        .remarks { background:#FEF3C7; border-left:4px solid #D97706; border-radius:6px; padding:14px 16px; margin:16px 0; }
        .remarks-label { font-size:10px; font-weight:700; color:#92400E; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .remarks-text { font-size:13px; color:#78350F; line-height:1.6; }
        .btn-wrap { text-align:center; margin:24px 0 8px; }
        .btn { display:inline-block; background:#1E3A8A; color:#ffffff; padding:12px 32px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; }
        .btn-gold { background:#D4AF37; }
        .otp-code { font-size:42px; font-weight:800; letter-spacing:12px; color:#1E3A8A; background:#F1F5F9; padding:20px; border-radius:12px; border:2px dashed #CBD5E1; text-align:center; }
        .footer { background:#F8FAFC; padding:18px 28px; border-radius:0 0 12px 12px; border-top:1px solid #E2E8F0; text-align:center; }
        .footer p { font-size:11px; color:#94A3B8; line-height:1.8; }
        .footer strong { color:#1E3A8A; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="header-logo">KMSAR</div>
            <div class="header-sub">Knowledge Management System for Academic Research · Angeles University Foundation</div>
            <div class="header-bar"></div>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p><strong>KMSAR</strong> · Angeles University Foundation · OVPRI · KMSAR System<br>
            This is an automated notification. Please do not reply to this email.<br>
            &copy; {{ date('Y') }} Angeles University Foundation. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
