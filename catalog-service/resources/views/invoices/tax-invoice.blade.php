<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#1a1a1a;margin:0;padding:30px}
.header{text-align:center;margin-bottom:20px}
.header h1{font-size:20px;margin:0 0 4px;color:#ff6b00}
.header p{font-size:11px;color:#666;margin:2px 0}
.title{text-align:center;border:2px solid #333;padding:8px;margin:16px 0;font-size:14px;font-weight:bold;letter-spacing:2px}
.section{margin-bottom:12px}
.section-title{font-weight:bold;font-size:13px;border-bottom:1px solid #ddd;padding-bottom:4px;margin-bottom:8px}
.row{display:flex;margin-bottom:4px}
.col-6{flex:1}.col-4{flex:1;max-width:33%}
.label{color:#666;font-size:10px}
.value{font-size:12px}
table{width:100%;border-collapse:collapse;margin:12px 0}
th{background:#f5f5f5;text-align:left;padding:6px 8px;font-size:10px;font-weight:bold;border-bottom:2px solid #333}
td{padding:6px 8px;font-size:11px;border-bottom:1px solid #eee}
.text-right{text-align:right}.text-center{text-align:center}
.total-row td{font-weight:bold;border-top:2px solid #333}
.in-words{border:1px solid #ddd;padding:10px;margin:12px 0;font-size:11px;background:#fafafa}
.bank-details{margin-top:16px;font-size:11px;line-height:1.6}
.signature{text-align:right;margin-top:40px}
.signature p{margin:6px 0}
.footer{text-align:center;font-size:9px;color:#999;margin-top:20px;border-top:1px solid #eee;padding-top:8px}
</style>
</head>
<body>

<div class="header">
    <h1>UtterLazy</h1>
    <p>GSTIN: {{ $aggGstin }}</p>
    <p>Food Delivery Aggregator | E-Commerce Operator</p>
</div>

<div class="title">TAX INVOICE</div>

<div class="section">
    <div class="section-title">Restaurant Details</div>
    <div class="row">
        <div class="col-6">
            <div class="label">Name</div>
            <div class="value">{{ $restaurant->name }}</div>
        </div>
        <div class="col-6">
            <div class="label">Address</div>
            <div class="value">{{ $restaurant->address ?? 'N/A' }}</div>
        </div>
    </div>
    @if($restaurant->gst_number)
    <div class="row" style="margin-top:4px">
        <div class="col-6">
            <div class="label">Restaurant GSTIN</div>
            <div class="value">{{ $restaurant->gst_number }}</div>
        </div>
        <div class="col-6">
            <div class="label">Place of Supply</div>
            <div class="value">{{ $restaurant->state ?? '29-Karnataka' }}</div>
        </div>
    </div>
    @endif
</div>

<div class="section">
    <div class="section-title">Invoice Details</div>
    <div class="row">
        <div class="col-4">
            <div class="label">Invoice Number</div>
            <div class="value"><strong>{{ $invoice->invoice_number }}</strong></div>
        </div>
        <div class="col-4">
            <div class="label">Invoice Date</div>
            <div class="value">{{ now()->format('d-m-Y') }}</div>
        </div>
        <div class="col-4">
            <div class="label">Type</div>
            <div class="value">{{ $typeLabel }}</div>
        </div>
    </div>
    <div class="row" style="margin-top:4px">
        <div class="col-6">
            <div class="label">Period</div>
            <div class="value">{{ \Carbon\Carbon::parse($invoice->period_start)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($invoice->period_end)->format('d-m-Y') }}</div>
        </div>
        <div class="col-6">
            <div class="label">Status</div>
            <div class="value">{{ ucfirst($invoice->status) }}</div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Particulars</div>
    <table>
        <thead>
            <tr>
                <th style="width:30px">Sl</th>
                <th>Particulars</th>
                <th style="width:80px">SAC</th>
                <th class="text-right" style="width:90px">Taxable Value</th>
                <th class="text-center" style="width:60px">CGST Rate</th>
                <th class="text-right" style="width:80px">CGST Amt</th>
                <th class="text-center" style="width:60px">SGST Rate</th>
                <th class="text-right" style="width:80px">SGST Amt</th>
                <th class="text-right" style="width:80px">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    @switch($invoice->type)
                        @case('commission')
                            Commission for usage of UtterLazy Online Ordering Platform ({{ number_format($invoice->commission_rate ?? 15, 0) }}% of order value net of discounts & tax)
                            @break
                        @case('ads')
                            Sponsored Advertisement Services
                            @break
                        @case('recovery')
                            Recovery Charges
                            @break
                        @default
                            {{ $typeLabel }}
                    @endswitch
                </td>
                <td>{{ $sac }}</td>
                <td class="text-right">{{ number_format($invoice->amount, 2) }}</td>
                <td class="text-center">{{ number_format($invoice->cgst_rate ?? 9, 0) }}%</td>
                <td class="text-right">{{ number_format($invoice->cgst_amount, 2) }}</td>
                <td class="text-center">{{ number_format($invoice->sgst_rate ?? 9, 0) }}%</td>
                <td class="text-right">{{ number_format($invoice->sgst_amount, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td></td>
                <td><strong>Total</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($invoice->amount, 2) }}</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($invoice->cgst_amount, 2) }}</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($invoice->sgst_amount, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($invoice->total_amount, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

@php
$amountInWords = ucwords((new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT))->format($invoice->total_amount)) . ' Rupees Only';
@endphp

<div class="in-words">
    <strong>Total Invoice Value (in words):</strong> {{ $amountInWords }}
</div>

<div class="bank-details">
    <div class="section-title">Bank Details — For Payment</div>
    <p><strong>Account Name:</strong> UtterLazy Technologies Pvt Ltd</p>
    <p><strong>Bank:</strong> State Bank of India</p>
    <p><strong>Account Number:</strong> 12345678901</p>
    <p><strong>IFSC Code:</strong> SBIN0001234</p>
    <p><strong>Branch:</strong> Bangalore Main Branch</p>
</div>

<div class="signature">
    <p><strong>For UtterLazy</strong></p>
    <p style="margin-top:30px">Authorized Signatory</p>
</div>

<div class="footer">
    This is a computer-generated invoice and does not require a physical signature.<br>
    Registered under Section 9(5) of CGST Act — E-Commerce Operator liable to pay GST.
</div>

</body>
</html>
