@php
    $formatCurrency = fn ($value) => system_currency_format($value);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('ui.seller_wise_reports') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; font-size: 12px; }
        h1, h2, h3, p { margin: 0 0 8px 0; }
        .muted { color: #6b7280; }
        .summary { width: 100%; border-collapse: collapse; margin: 16px 0 24px; }
        .summary td { border: 1px solid #d1d5db; padding: 10px; vertical-align: top; }
        .summary .label { color: #6b7280; font-size: 11px; display: block; margin-bottom: 4px; }
        .summary .value { font-size: 16px; font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th, .table td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        .table th { background: #f3f4f6; }
        .section { margin-top: 18px; }
        .seller-box { margin-bottom: 18px; }
        .small { font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ __('ui.seller_wise_reports') }}</h1>
    <p class="muted">{{ __('ui.from') }}: {{ $rangeLabel }}</p>
    <p class="muted">{{ __('ui.seller') }}: {{ $selectedSellerName ?? __('ui.all_sellers') }}</p>

    <table class="summary">
        <tr>
            <td><span class="label">{{ __('ui.sellers') }}</span><span class="value">{{ number_format($summary['seller_count']) }}</span></td>
            <td><span class="label">{{ __('ui.shipped_orders') }}</span><span class="value">{{ number_format($summary['shipped_orders']) }}</span></td>
            <td><span class="label">{{ __('ui.calls') }}</span><span class="value">{{ number_format($summary['call_count']) }}</span></td>
            <td><span class="label">{{ __('ui.returns_label') }}</span><span class="value">{{ number_format($summary['return_count']) }}</span></td>
        </tr>
    </table>

    <div class="section">
        <h2>{{ __('ui.seller_summary') }}</h2>
        @foreach ($sellerRows as $seller)
            <div class="seller-box">
                <h3>{{ $seller['seller_name'] }}</h3>
                <p class="small muted">{{ $seller['seller_email'] }}</p>
                <p class="small">{{ __('ui.shipped_orders') }}: {{ number_format($seller['shipped_orders']) }} | {{ __('ui.calls') }}: {{ number_format($seller['calls']) }} | {{ __('ui.returns_label') }}: {{ number_format($seller['returns']) }} | {{ __('ui.shipped_value') }}: {{ $formatCurrency($seller['shipped_amount']) }}</p>

                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.order') }}</th>
                            <th>{{ __('ui.customer') }}</th>
                            <th>{{ __('ui.courier') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.amount') }}</th>
                            <th>{{ __('ui.shipment_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($seller['orders'] as $order)
                            <tr>
                                <td>{{ $order['order_id'] }}</td>
                                <td>{{ $order['customer_name'] }}</td>
                                <td>{{ $order['courier_name'] }}</td>
                                <td>{{ __('ui.' . strtolower((string) $order['status'])) }}</td>
                                <td>{{ $formatCurrency($order['amount']) }}</td>
                                <td>{{ $order['shipment_date'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">{{ __('ui.no_shipped_orders_for_this_seller_in_the_selected_range') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="section">
        <h2>{{ __('ui.all_shipped_order_details') }}</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('ui.seller') }}</th>
                    <th>{{ __('ui.order') }}</th>
                    <th>{{ __('ui.customer') }}</th>
                    <th>{{ __('ui.courier') }}</th>
                    <th>{{ __('ui.status') }}</th>
                    <th>{{ __('ui.amount') }}</th>
                    <th>{{ __('ui.shipment_date') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shipmentDetailRows as $row)
                    <tr>
                        <td>{{ $row['seller_name'] }}</td>
                        <td>{{ $row['order_id'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td>{{ $row['courier_name'] }}</td>
                        <td>{{ __('ui.' . strtolower((string) $row['status'])) }}</td>
                        <td>{{ $formatCurrency($row['amount']) }}</td>
                        <td>{{ $row['shipment_date'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">{{ __('ui.no_shipped_order_details_found') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
