<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipment Label - {{ $shipment->shipment_code }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #d7dde7;
            --accent: #2563eb;
            --soft: #f8fafc;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #e2e8f0;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .toolbar {
            width: min(760px, 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 14px;
        }

        .toolbar button {
            border: 0;
            background: var(--accent);
            color: #fff;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }

        .label {
            width: min(760px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            border-radius: 18px;
            overflow: hidden;
        }

        .label-header {
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: #fff;
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .subtitle {
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 13px;
        }

        .code-badge {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 12px 16px;
            text-align: right;
            min-width: 180px;
        }

        .code-badge .label-text {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.72);
            margin-bottom: 6px;
        }

        .code-badge .value {
            font-size: 22px;
            font-weight: 800;
        }

        .label-body {
            padding: 24px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .meta-card {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
        }

        .meta-card .key {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .meta-card .value {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
        }

        .addresses {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            min-height: 170px;
        }

        .panel h3 {
            margin: 0 0 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--accent);
        }

        .panel p {
            margin: 0 0 6px;
            line-height: 1.45;
            font-size: 14px;
            color: var(--ink);
        }

        .panel .muted {
            color: var(--muted);
        }

        .footer {
            margin-top: 16px;
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 16px;
            align-items: stretch;
        }

        .barcode {
            border: 1px dashed var(--line);
            border-radius: 16px;
            padding: 18px;
            background: repeating-linear-gradient(
                90deg,
                #0f172a 0,
                #0f172a 2px,
                #fff 2px,
                #fff 6px
            );
            min-height: 94px;
        }

        .barcode span {
            display: inline-block;
            background: rgba(255, 255, 255, 0.92);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .barcode .ref {
            margin-top: 10px;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #fff;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.35);
        }

        .note {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            background: #fff;
        }

        .note strong {
            display: block;
            margin-bottom: 6px;
        }

        .note p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .label {
                width: 100%;
                box-shadow: none;
                border-radius: 0;
                border: 0;
            }

            @page {
                size: auto;
                margin: 8mm;
            }
        }

        @media (max-width: 768px) {
            .meta-grid,
            .addresses,
            .footer {
                grid-template-columns: 1fr;
            }

            .label-header {
                flex-direction: column;
            }

            .code-badge {
                width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            Print preview for shipment <strong>#{{ $shipment->shipment_code }}</strong>
        </div>
        <button type="button" onclick="window.print()">Print Label</button>
    </div>

    @php
        $customerName = $shipment->customer_name ?: $shipment->order?->customer?->name ?: 'Customer';
        $customerPhone = $shipment->customer_phone ?: $shipment->order?->customer?->phone ?: '-';
        $orderId = $shipment->order?->external_order_id ?: '-';
        $statusLabel = ucfirst(str_replace('_', ' ', (string) $shipment->status));
        $shipDate = optional($shipment->shipment_date)->format('d M Y') ?: '-';
        $pickupAddress = trim(collect([
            $shipment->pickup_name,
            $shipment->pickup_address,
            $shipment->pickup_city,
            $shipment->pickup_postal_code,
        ])->filter()->implode(', '));
        $deliveryAddress = trim(collect([
            $shipment->delivery_name,
            $shipment->delivery_address,
            $shipment->delivery_city,
            $shipment->delivery_postal_code,
        ])->filter()->implode(', '));
    @endphp

    <main class="label" role="document" aria-label="Shipment label">
        <section class="label-header">
            <div>
                <div class="brand">{{ $companyName }}</div>
                <div class="subtitle">Courier-ready shipment label</div>
            </div>
            <div class="code-badge">
                <span class="label-text">Shipment ID</span>
                <div class="value">#{{ $shipment->shipment_code }}</div>
            </div>
        </section>

        <section class="label-body">
            <div class="meta-grid">
                <div class="meta-card">
                    <span class="key">Courier</span>
                    <div class="value">{{ $shipment->courier_name ?: 'Not assigned' }}</div>
                </div>
                <div class="meta-card">
                    <span class="key">Order</span>
                    <div class="value">{{ $orderId }}</div>
                </div>
                <div class="meta-card">
                    <span class="key">Status / Date</span>
                    <div class="value">{{ $statusLabel }} · {{ $shipDate }}</div>
                </div>
            </div>

            <div class="addresses">
                <div class="panel">
                    <h3>Pickup From</h3>
                    <p><strong>{{ $shipment->pickup_name ?: ($shipment->seller?->name ?? 'Warehouse') }}</strong></p>
                    <p class="muted">{{ $pickupAddress ?: 'Pickup address not provided yet.' }}</p>
                </div>
                <div class="panel">
                    <h3>Deliver To</h3>
                    <p><strong>{{ $customerName }}</strong></p>
                    <p class="muted">{{ $deliveryAddress ?: 'Delivery address not provided yet.' }}</p>
                    <p class="muted">Phone: {{ $customerPhone }}</p>
                </div>
            </div>

            <div class="footer">
                <div class="barcode">
                    <span>Reference for scanning / courier handoff</span>
                    <div class="ref">{{ $shipment->shipment_code }}</div>
                </div>
                <div class="note">
                    <strong>Print note</strong>
                    <p>
                        This page is ready for browser printing now. When courier label integration is connected,
                        we can swap this section to render the courier-provided PDF, ZPL, or tracking label payload.
                    </p>
                    <p style="margin-top:10px;">Generated at {{ $printedAt->format('d M Y, h:i A') }}</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });

        window.addEventListener('afterprint', function () {
            if (window.opener) {
                window.close();
            }
        });
    </script>
</body>
</html>
