<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $htmlDir ?? 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt #{{ $order->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            background: #fff;
        }
        
        .receipt-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #000;
        }
        
        .header p {
            font-size: 11px;
            color: #666;
        }
        
        .order-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #000;
        }
        
        .items-section {
            margin-top: 30px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #000;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            margin-top: 20px;
            border-top: 2px solid #000;
            padding-top: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        
        .total-row.grand-total {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        
        .status-received {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-mixing {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-ready {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .modifier-item {
            font-size: 10px;
            color: #666;
            margin-left: 15px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>Order Receipt</h1>
            <p>{{ $store->name ?? 'Kippis Store' }}</p>
            @if($store?->address)
                <p>{{ $store->address }}</p>
            @endif
        </div>
        
        <div class="order-info">
            <div class="info-row">
                <span class="info-label">Order #:</span>
                <span class="info-value">{{ $order->id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Pickup Code:</span>
                <span class="info-value"><strong>{{ $order->pickup_code }}</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value">{{ $order->created_at->format('F d, Y h:i A') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">
                    <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                </span>
            </div>
            @if($customer)
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span class="info-value">{{ $customer->name ?? 'N/A' }}</span>
                </div>
                @if($customer->phone ?? null)
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">{{ ($customer->country_code ?? '') . ($customer->phone ?? '') }}</span>
                    </div>
                @endif
            @endif
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">
                    @if($order->paymentMethod)
                        {{ $order->paymentMethod->name }} ({{ $order->paymentMethod->code }})
                    @else
                        {{ ucfirst($order->payment_method) }}
                    @endif
                </span>
            </div>
        </div>
        
        <div class="items-section">
            <div class="section-title">Order Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items_snapshot as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['product_name'] ?? 'Product' }}</strong>
                                @if(isset($item['modifiers']) && is_array($item['modifiers']) && count($item['modifiers']) > 0)
                                    @foreach($item['modifiers'] as $modifier)
                                        @if(is_array($modifier) && isset($modifier['name']))
                                            <div class="modifier-item">+ {{ $modifier['name'] }}
                                                @if(isset($modifier['price']) && $modifier['price'] > 0)
                                                    ({{ number_format($modifier['price'], 2) }})
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                            <td class="text-right">{{ number_format($item['price'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>{{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->discount > 0)
                <div class="total-row">
                    <span>Discount:
                        @if($order->promoCode?->code)
                            ({{ $order->promoCode->code }})
                        @endif
                    </span>
                    <span>-{{ number_format($order->discount, 2) }}</span>
                </div>
            @endif
            @if($order->tax > 0)
                <div class="total-row">
                    <span>Tax:</span>
                    <span>{{ number_format($order->tax, 2) }}</span>
                </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>{{ number_format($order->total, 2) }}</span>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you for your order!</p>
            <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
        </div>
    </div>
</body>
</html>

