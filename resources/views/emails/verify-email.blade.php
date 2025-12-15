<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Verify your email</title>
</head>
<body style="margin:0;padding:0;font-family:'Inter',Arial,sans-serif;background:#f4f7fb;color:#0f172a;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f7fb;padding:32px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);">
          <tr>
            <td style="padding:32px;background:linear-gradient(135deg,#0ea5e9,#10b981);color:#ffffff;">
              <table width="100%" role="presentation">
                <tr>
                  <td style="font-size:24px;font-weight:700;">TechService</td>
                  <td align="right" style="font-size:14px;opacity:0.9;">100% Free Technical Support</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 12px;font-size:24px;color:#0f172a;">Activate your account</h1>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1e293b;">Hi {{ $user->full_name ?: 'there' }},</p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1e293b;">Thanks for signing up with TechService. Please confirm your email to start getting support.</p>
              <div style="margin:24px 0;text-align:center;">
                <a href="{{ $verificationUrl }}" style="display:inline-block;background:#10b981;color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:14px;font-weight:700;box-shadow:0 10px 30px rgba(16,185,129,0.35);">Verify email</a>
              </div>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">This link will expire in 60 minutes. If the button doesn’t work, copy and paste this URL into your browser:</p>
              <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#0ea5e9;word-break:break-all;">{{ $verificationUrl }}</p>
              <p style="margin:0;font-size:14px;line-height:1.6;color:#94a3b8;">If you didn’t create a TechService account, you can safely ignore this email.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px;background:#f8fafc;color:#94a3b8;font-size:12px;text-align:center;">
              TechService &bull; Reliable support for your tech needs
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

