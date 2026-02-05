<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1E7CCF;
        }

        .invoice-details {
            text-align: right;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .table th {
            background-color: #f8f8f8;
            font-weight: bold;
        }

        .total-section {
            text-align: right;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            margin-bottom: 5px;
        }

        .grand-total {
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-unpaid {
            background: #fef9c3;
            color: #854d0e;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>

    <table width="100%">
        <tr>
            <td valign="top">
                <div class="logo">{{ config('app.name') }}</div>
                <p>
                    123 SaaS Street<br>
                    Tech City, TC 90210<br>
                    support@example.com
                </p>
            </td>
            <td valign="top" align="right">
                <h1 style="margin: 0 0 10px 0;">INVOICE</h1>
                <p><strong>#{{ $invoice->invoice_number }}</strong></p>
                <p>Date: {{ $invoice->date->format('M d, Y') }}</p>
                <p>Due Date: {{ $invoice->due_date->format('M d, Y') }}</p>

                <div style="margin-top: 10px;">
                    @if($invoice->status === 'Paid')
                        <span class="status status-paid">PAID</span>
                    @elseif($invoice->status === 'Unpaid')
                        <span class="status status-unpaid">UNPAID</span>
                    @else
                        <span class="status status-cancelled">{{ strtoupper($invoice->status) }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 40px; margin-bottom: 40px;">
        <strong>Bill To:</strong><br>
        {{ $invoice->user->name }}<br>
        {{ $invoice->user->email }}
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th align="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td align="right">{{ $item->formatted_amount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <p><strong>Total: {{ $invoice->formatted_total }}</strong></p>
    </div>

    @if($invoice->transactions->isNotEmpty())
        <div style="margin-top: 40px;">
            <h3>Transactions</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th align="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->transactions as $txn)
                        <tr>
                            <td>{{ $txn->date->format('M d, Y') }}</td>
                            <td>{{ $txn->payment_method }}</td>
                            <td>{{ $txn->transaction_id ?? '-' }}</td>
                            <td align="right">{{ number_format($txn->amount / 100, 2) }} {{ $invoice->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</body>

</html>