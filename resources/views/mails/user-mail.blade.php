<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - OTP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            line-height: 1.6;
            color: #2c3e50;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: linear-gradient(to bottom, #ffffff 0%, #f8f9fb 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }

        /* <CHANGE> Vibrant gradient header with white logo area */
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        /* <CHANGE> White background section for PNG logo */
        .logo-section {
            background-color: #ffffff;
            padding: 30px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .logo {
            max-width: 100px;
            height: auto;
            margin: 0 auto;
        }

        .logo img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* <CHANGE> Modern header with gradient text effect */
        .header-content {
            position: relative;
            z-index: 1;
            color: #ffffff;
        }

        .header-content h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header-content p {
            font-size: 15px;
            font-weight: 400;
            opacity: 0.95;
        }

        .content {
            padding: 45px 35px;
        }

        .greeting {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .message {
            font-size: 15px;
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        /* <CHANGE> Eye-catching OTP display with gradient background and shadow */
        .otp-section {
            background: linear-gradient(135deg, #eaac66ff 0%, #e27814ff 100%);
            padding: 50px 40px;
            border-radius: 12px;
            margin: 40px 0;
            text-align: center;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
        }

        .otp-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .otp-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.85);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 18px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .otp-code {
            font-family: 'Courier New', monospace;
            font-size: 48px;
            font-weight: 800;
            color: #000000ff;
            letter-spacing: 8px;
            word-break: break-all;
            margin-bottom: 20px;
            font-kerning: none;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .otp-expiry {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        /* <CHANGE> Modern info boxes with accent colors */
        .info-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            padding: 10px;
            border-left: 5px solid #667eea;
            border-radius: 8px;
            margin: 30px 0;
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.8;
        }

        .info-box strong {
            color: #667eea;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .instructions {
            background: #f8f9fb;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 30px 0;
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.4;
        }

        .instructions strong {
            display: block;
            margin-bottom: 10px;
            color: #667eea;
            font-weight: 600;
        }

        .instructions ol {
            margin-left: 20px;
            margin-top: 12px;
        }

        .instructions li {
            margin-bottom: 12px;
        }

        /* <CHANGE> Attractive security warning with warm accent */
        .security-warning {
            background: linear-gradient(135deg, #fff5e6 0%, #ffe8cc 100%);
            border-left: 5px solid #ff9800;
            padding: 10px;
            border-radius: 8px;
            margin: 30px 0;
            font-size: 13px;
            color: #5d4037;
            line-height: 1.8;
        }

        .security-warning strong {
            display: block;
            margin-bottom: 8px;
            color: #ff9800;
            font-weight: 600;
        }

        /* <CHANGE> Modern footer with gradient accent */
        .footer {
            background: linear-gradient(to right, #f8f9fb 0%, #f0f2f5 100%);
            padding: 15px 30px;
            text-align: center;
            border-top: 2px solid #e9ecef;
        }

        .footer-text {
            font-size: 13px;
            color: #666;
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .footer-links {
            font-size: 12px;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px solid #e9ecef;
        }

        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .email-container {
                width: 100%;
                margin: 0;
                border-radius: 0;
            }

            .content {
                padding: 30px 20px;
            }

            .logo-section {
                padding: 5px 5px;
            }

            .header-gradient {
                padding: 40px 20px;
            }

            .header-content h1 {
                font-size: 28px;
            }

            .otp-code {
                font-size: 40px;
                letter-spacing: 6px;
            }

            .otp-section {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Gradient Header -->
        <div class="header-gradient">
            <div class="header-content">
                <h1>{{ $data['subject'] }}</h1>
                <p>Enter your one-time password to continue</p>
            </div>
        </div>

        <!-- Logo Section with White Background -->
        <div class="logo-section">
            <div class="logo">
                <!-- Replace with your PNG logo -->
                <img src="{{ config('app.url') }}/assets/images/logo.png" width="200" alt="Company Logo">
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Hi {{$data['name']}},</p>

            <div class="message">
                <p>{{$data['message']}}</p>
            </div>

            <!-- OTP Display -->
            <div class="otp-section">
                <div class="otp-label">Your One-Time Password</div>
                <div class="otp-code">{{$data['otp']}}</div>
                <div class="otp-expiry">⏱ Expires in 10 minutes</div>
            </div>

            <!-- Important Info -->
            <div class="info-box">
                <strong>⚠️ Important:</strong> This code is valid for only 10 minutes. If it expires, you can request a new one from the verification page.
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <strong>How to use your OTP:</strong>
                <ol>
                    <li>Go back to the verification page</li>
                    <li>Enter the 4-character code shown above</li>
                    <li>Click "Verify" to complete your email verification</li>
                </ol>
            </div>

            <!-- Security Warning -->
            <div class="security-warning">
                <strong>🔐 Security Reminder:</strong>
                Never share this code with anyone. We will never ask for your OTP via email or phone. If you didn't request this verification, please ignore this email and contact our support team immediately.
            </div>

            <div class="message">
                <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                © {{ date('Y') }} Upliffting. All rights reserved.<br>
                This is an automated message, please do not reply to this email.
            </p>
            <div class="footer-links">
                <a href="https://example.com/help">Help Center</a>
                <a href="https://example.com/privacy">Privacy Policy</a>
                <a href="https://example.com/terms">Terms of Service</a>
            </div>
        </div>
    </div>
</body>
</html>