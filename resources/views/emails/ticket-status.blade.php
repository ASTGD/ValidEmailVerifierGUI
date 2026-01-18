<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Status Update</title>
</head>

<body
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; line-height: 1.6; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="background-color: #1E7CCF; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Ticket Status Update</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #334155; font-size: 16px;">Hello,</p>
                            <p style="color: #334155; font-size: 16px;">Your support ticket
                                <strong>#{{ $ticket->ticket_number }}</strong> has been updated.</p>

                            <div style="background-color: #f1f5f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <p style="margin: 5px 0;"><strong>Subject:</strong> {{ $ticket->subject }}</p>
                                <p style="margin: 5px 0;"><strong>New Status:</strong>
                                    @if($action === 'deleted')
                                        <span style="color: #ef4444; font-weight: bold;">Deleted</span>
                                    @else
                                        <span
                                            style="color: #1E7CCF; font-weight: bold;">{{ $ticket->status->label() }}</span>
                                    @endif
                                </p>
                            </div>

                            <p style="color: #334155;">If you have any questions, please contact support.</p>

                            @if($action !== 'deleted')
                                <div style="text-align: center; margin-top: 30px;">
                                    <a href="{{ config('app.url') }}"
                                        style="background-color: #1E7CCF; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">View
                                        Ticket</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="background-color: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>