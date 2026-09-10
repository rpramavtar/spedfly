<?php

namespace App\Services;

use App\Models\CallCenterLog;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Shipment;
use App\Models\SellerFeeRule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SellerFeeService
{
    public function feeTypeOptions(): array
    {
        return [
            'delivery' => 'Order Delivered Fee',
            'shipping' => 'Shipping Fee',
            'return' => 'Return Fee',
            'call' => 'Call Fee',
            'order_code' => 'Order Code Fee',
        ];
    }

    public function billingUnitOptions(): array
    {
        return [
            'monthly_orders' => 'Monthly Orders',
            'delivered_orders' => 'Delivered Orders',
            'shipments' => 'Shipments',
            'returns' => 'Returns',
            'calls' => 'Calls',
            'order_codes' => 'Order Codes',
        ];
    }

    public function buildDashboardSummary(User $seller, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        $periodStart ??= now()->startOfMonth()->startOfDay();
        $periodEnd ??= now()->endOfMonth()->endOfDay();

        $rules = $seller->feeRules()
            ->where('is_active', true)
            ->orderBy('fee_type')
            ->orderBy('billing_unit')
            ->orderByRaw('COALESCE(min_quantity, 0) ASC')
            ->get();

        $metrics = $this->buildMetrics($seller, $periodStart, $periodEnd);
        $cards = [];

        foreach ($rules->groupBy(fn (SellerFeeRule $rule) => $rule->fee_type . ':' . $rule->billing_unit) as $group) {
            /** @var Collection<int, SellerFeeRule> $group */
            $sortedRules = $group->sort(function (SellerFeeRule $a, SellerFeeRule $b) {
                $aMin = $a->min_quantity ?? 0;
                $bMin = $b->min_quantity ?? 0;

                if ($aMin === $bMin) {
                    $aMax = $a->max_quantity ?? PHP_INT_MAX;
                    $bMax = $b->max_quantity ?? PHP_INT_MAX;

                    return $aMax <=> $bMax;
                }

                return $aMin <=> $bMin;
            })->values();

            $rule = $this->matchRuleForMetric($sortedRules, (int) data_get($metrics, $sortedRules->first()->billing_unit, 0));

            if (! $rule) {
                continue;
            }

            $quantity = (int) data_get($metrics, $rule->billing_unit, 0);
            $amount = (float) $quantity * (float) $rule->rate;

            $cards[] = [
                'key' => $rule->fee_type . ':' . $rule->billing_unit,
                'label' => $this->feeTypeOptions()[$rule->fee_type] ?? ucfirst(str_replace('_', ' ', $rule->fee_type)),
                'icon' => match ($rule->fee_type) {
                    'delivery' => 'bi-truck',
                    'shipping' => 'bi-truck',
                    'return' => 'bi-arrow-counterclockwise',
                    'call' => 'bi-telephone',
                    'order_code' => 'bi-upc-scan',
                    default => 'bi-receipt',
                },
                'amount' => round($amount, 2),
                'rate' => (float) $rule->rate,
                'currency' => system_currency_code(),
                'quantity' => $quantity,
                'quantity_label' => $this->billingUnitOptions()[$rule->billing_unit] ?? ucfirst(str_replace('_', ' ', $rule->billing_unit)),
                'tier_label' => $this->formatTier($rule->min_quantity, $rule->max_quantity),
                'notes' => $rule->label ?: ($this->feeTypeOptions()[$rule->fee_type] ?? $rule->fee_type),
            ];
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'metrics' => $metrics,
            'cards' => $cards,
            'total_amount' => round(collect($cards)->sum('amount'), 2),
        ];
    }

    public function buildMetrics(User $seller, Carbon $periodStart, Carbon $periodEnd): array
    {
        $ordersBaseQuery = Order::query()
            ->where('seller_id', $seller->id)
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->whereBetween('ordered_at', [$periodStart, $periodEnd]);

        return [
            'monthly_orders' => (clone $ordersBaseQuery)->count(),
            'delivered_orders' => (clone $ordersBaseQuery)->whereRaw('LOWER(COALESCE(status, "")) = ?', ['delivered'])->count(),
            'shipments' => Shipment::query()
                ->where('seller_id', $seller->id)
                ->whereBetween('shipment_date', [$periodStart, $periodEnd])
                ->count(),
            'returns' => OrderReturn::query()
                ->where('seller_id', $seller->id)
                ->whereBetween('returned_at', [$periodStart, $periodEnd])
                ->count(),
            'calls' => CallCenterLog::query()
                ->where('seller_id', $seller->id)
                ->whereBetween('call_time', [$periodStart, $periodEnd])
                ->count(),
            'order_codes' => (clone $ordersBaseQuery)
                ->whereNotNull('external_order_id')
                ->where('external_order_id', '!=', '')
                ->count(),
        ];
    }

    /**
     * @param Collection<int, SellerFeeRule> $sortedRules
     */
    private function matchRuleForMetric(Collection $sortedRules, int $quantity): ?SellerFeeRule
    {
        foreach ($sortedRules as $rule) {
            $min = $rule->min_quantity ?? 0;
            $max = $rule->max_quantity;

            if ($quantity < $min) {
                continue;
            }

            if ($max !== null && $quantity > $max) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    private function formatTier(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return 'All volumes';
        }

        if ($min !== null && $max === null) {
            return $min . '+';
        }

        if ($min === null && $max !== null) {
            return 'Up to ' . $max;
        }

        return $min . ' - ' . $max;
    }
}
