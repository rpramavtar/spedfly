@include('spedfly.include.header')

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
          <div>
            <h1 class="h3 text-gray-800">{{ $seller->name }} - {{ __('ui.wallet') }}</h1>
            <p class="text-muted">Manage seller wallet balance, transactions, and notification threshold.</p>
          </div>
          <div>
            <a href="{{ route('admin.sellers') }}" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left"></i> {{ __('ui.back') }}
            </a>
          </div>
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <!-- 3 Stat Cards -->
        <div class="row mb-4">
          <div class="col-md-4">
            <div class="card shadow-sm p-4 text-center border-0 bg-white">
              <span class="text-muted small fw-bold uppercase tracking-wider">{{ __('ui.current_balance') }}</span>
              <h2 class="display-6 fw-bold text-dark mt-2">{{ system_currency_format($wallet->balance) }}</h2>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card shadow-sm p-4 text-center border-0 bg-white">
              <span class="text-muted small fw-bold uppercase tracking-wider">{{ __('ui.minimum_threshold') }}</span>
              <h2 class="display-6 fw-bold text-secondary mt-2">{{ system_currency_format($wallet->min_threshold) }}</h2>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card shadow-sm p-4 text-center border-0 bg-white">
              <span class="text-muted small fw-bold uppercase tracking-wider">{{ __('ui.orders_this_month') }}</span>
              <h2 class="display-6 fw-bold text-primary mt-2">{{ number_format($ordersThisMonth) }}</h2>
            </div>
          </div>
        </div>

        <!-- Balance Utilization / Progress Bar -->
        @php
          $maxRef = max($wallet->min_threshold * 2, 200.00);
          $percentage = min(100, max(0, ($wallet->balance / $maxRef) * 100));
          $barColor = 'bg-success';
          if ($wallet->balance < $wallet->min_threshold) {
              $barColor = 'bg-danger';
          } elseif ($wallet->balance < $wallet->min_threshold * 1.5) {
              $barColor = 'bg-warning';
          }
        @endphp
        <div class="card shadow-sm border-0 mb-4 bg-white">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="card-title fw-bold text-dark mb-0">{{ __('ui.balance_usage') }}</h5>
              <span class="text-muted small fw-semibold">{{ number_format($percentage, 0) }}% {{ __('ui.of_initial_deposit') }}</span>
            </div>
            <div class="progress mb-2" style="height: 12px; border-radius: 6px;">
              <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $wallet->balance }}" aria-valuemin="0" aria-valuemax="{{ $maxRef }}"></div>
            </div>
            <div class="d-flex justify-content-between text-muted small">
              <span>{{ system_currency_format(0) }}</span>
              <span>{{ system_currency_format($maxRef) }}</span>
            </div>
          </div>
        </div>

        <!-- Transaction History Table -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
          <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="fw-bold text-dark mb-0">{{ __('ui.recent_movements') }}</h5>
            <div>
              <button
                class="btn btn-primary btn-sm me-2"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#topupWalletModal"
              >
                {{ __('ui.adjust_balance') }}
              </button>
              <button
                class="btn btn-outline-secondary btn-sm"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#thresholdWalletModal"
              >
                {{ __('ui.change_threshold') }}
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="px-4 py-3">{{ __('ui.date') }}</th>
                    <th class="py-3">{{ __('ui.description') }}</th>
                    <th class="py-3">{{ __('ui.type') }}</th>
                    <th class="text-end py-3">{{ __('ui.amount') }}</th>
                    <th class="text-end px-4 py-3">{{ __('ui.balance_after') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($transactions as $tx)
                    @php
                      $badgeClass = 'bg-secondary';
                      $typeName = ucfirst($tx->type);
                      if ($tx->type === 'charge') {
                          $badgeClass = 'bg-light text-danger';
                          $typeName = __('ui.charge_badge');
                      } elseif ($tx->type === 'topup') {
                          $badgeClass = 'bg-light text-success';
                          $typeName = __('ui.topup_badge');
                      } elseif ($tx->type === 'refund') {
                          $badgeClass = 'bg-light text-primary';
                          $typeName = __('ui.refund_badge');
                      }
                    @endphp
                    <tr>
                      <td class="px-4 py-3 text-muted small">{{ $tx->created_at->format('d/m Y H:i') }}</td>
                      <td class="py-3 fw-semibold text-dark">
                        {{ $tx->description }}
                        @if ($tx->reference_id)
                          <br><small class="text-muted">Order ID: #{{ $tx->reference_id }}</small>
                        @endif
                      </td>
                      <td class="py-3"><span class="badge {{ $badgeClass }} border px-2 py-1">{{ $typeName }}</span></td>
                      <td class="text-end py-3 fw-bold {{ $tx->amount < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $tx->amount < 0 ? '-' : '+' }}{{ system_currency_format(abs($tx->amount)) }}
                      </td>
                      <td class="text-end px-4 py-3 fw-bold text-dark">{{ system_currency_format($tx->balance_after) }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="text-center py-4 text-muted">{{ __('ui.no_transactions_recorded_yet') }}</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            @if ($transactions->hasPages())
              <div class="card-footer bg-white border-top py-3 px-4">
                {{ $transactions->links() }}
              </div>
            @endif
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Topup/Deduct Wallet Modal -->
<div class="modal fade" id="topupWalletModal" tabindex="-1" aria-labelledby="topupWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h5 class="modal-title fw-semibold" id="topupWalletModalLabel">{{ __('ui.adjust_balance') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form action="{{ route('admin.sellers.wallet.topup', $seller) }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <p class="text-muted">Adjusting wallet balance for seller <strong>{{ $seller->name }}</strong></p>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('ui.adjustment_type') }}</label>
            <select name="type" class="form-select" required>
              <option value="topup">{{ __('ui.top_up_add_balance') }}</option>
              <option value="deduct">{{ __('ui.deduct_charge_balance') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('ui.amount') }} ({{ system_currency_symbol() }})</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required placeholder="0.00">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('ui.description_reason') }}</label>
            <input type="text" name="description" class="form-control" placeholder="{{ __('ui.eg_manual_adjustments_loyalty_credit') }}">
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_adjustment') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Threshold Wallet Modal -->
<div class="modal fade" id="thresholdWalletModal" tabindex="-1" aria-labelledby="thresholdWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h5 class="modal-title fw-semibold" id="thresholdWalletModalLabel">{{ __('ui.change_threshold') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form action="{{ route('admin.sellers.wallet.threshold', $seller) }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <p class="text-muted">Setting minimum threshold for seller <strong>{{ $seller->name }}</strong></p>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('ui.minimum_balance_threshold') }} ({{ system_currency_symbol() }})</label>
            <input type="number" step="0.01" min="0" name="min_threshold" id="thresholdAmountInput" class="form-control" required value="{{ $wallet->min_threshold }}">
            <small class="text-muted">A notification is sent to the seller when their wallet balance drops below this threshold.</small>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.update_threshold') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var $autoHide = $('.js-auto-hide-alert');
    if ($autoHide.length) {
      setTimeout(function () {
        $autoHide.fadeOut(300);
      }, 5000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
