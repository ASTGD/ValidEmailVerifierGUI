<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.6;
            color: #334155;
        }

        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .header {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 20px;
            color: #1e7ccf;
        }

        .message-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .footer {
            font-size: 12px;
            color: #64748b;
            margin-top: 30px;
        }

        .btn {
            background: #1e7ccf;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">Support Update: Ticket #{{ $ticket->ticket_number }}</div>

        <p>Hello,</p>
        <p>{{ $isAdminReply ? 'Our support team has replied to your ticket:' : 'A customer has sent a message regarding their ticket:' }}
        </p>

        <div class="message-box">
            <strong>Subject:</strong> {{ $ticket->subject }} <br><br>
            {{ $supportMessage->content }}
        </div>

        <p>You can view the full conversation and reply by clicking the button below:</p>

        <a href="{{ route('portal.support.show', $ticket) }}" class="btn">View Conversation</a>

        <div class="footer">
            This is an automated notification from ValidEmail. Please do not reply to this email directly.
        </div>
    </div>
</body>

</html>
