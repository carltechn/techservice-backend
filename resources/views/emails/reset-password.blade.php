<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Reset your password</title>
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
                  <td align="right" style="font-size:14px;opacity:0.9;">Secure password reset</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 12px;font-size:24px;color:#0f172a;">Reset your password</h1>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1e293b;">Hi {{ $user->full_name ?: 'there' }},</p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1e293b;">We received a request to reset your TechService password. Click the button below to choose a new one.</p>
              <div style="margin:24px 0;text-align:center;">
                <a href="{{ $resetUrl }}" style="display:inline-block;background:#0ea5e9;color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:14px;font-weight:700;box-shadow:0 10px 30px rgba(14,165,233,0.35);">Create new password</a>
              </div>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">If you didn’t request this, you can safely ignore this email. Your password will not change.</p>
              <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#0ea5e9;word-break:break-all;">{{ $resetUrl }}</p>
              <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">For security, this link will expire after a short time.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px;background:#f8fafc;color:#94a3b8;font-size:12px;text-align:center;">
              TechService • Helping you stay secure
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

