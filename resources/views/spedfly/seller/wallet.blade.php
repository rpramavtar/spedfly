@include('spedfly.include.header')

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
          <div>
            <h1 class="h3 text-gray-800">{{ __('ui.wallet') }}</h1>
            <p class="text-muted">View your current balance, transaction history, and request payouts.</p>
          </div>
          @if(!Auth::user()->shopify_shop_domain)
          <div>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#topupWalletModal">
              <i class="bi bi-plus-circle me-1"></i>{{ __('ui.top_up_add_balance') }}
            </button>
          </div>
          @else
          <div>
            <span class="text-muted small">Your available balance can be used for shipment and label generation.</span>
          </div>
          @endif
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if (session('error'))
          <div class="alert alert-danger js-auto-hide-alert">{{ session('error') }}</div>
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

        <!-- Stat Cards & Withdrawal Form -->
        <div class="row mb-4">
          <div class="col-lg-8">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="card shadow-sm p-4 text-center border-0 bg-white h-100">
                  <span class="text-muted small fw-bold uppercase tracking-wider">{{ __('ui.current_balance') }}</span>
                  <h2 class="display-5 fw-bold text-dark mt-2">{{ system_currency_format($wallet->balance) }}</h2>
                  @if ($wallet->balance < $wallet->min_threshold)
                    <div class="badge bg-light text-danger border mt-2">Balance is below threshold</div>
                  @endif
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm p-4 text-center border-0 bg-white h-100">
                  <span class="text-muted small fw-bold uppercase tracking-wider">{{ __('ui.minimum_threshold') }}</span>
                  <h2 class="display-5 fw-bold text-secondary mt-2">{{ system_currency_format($wallet->min_threshold) }}</h2>
                  <small class="text-muted mt-2">Notify limit set by admin</small>
                </div>
              </div>
            </div>
            
            <!-- Progress Usage Bar -->
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
            <div class="card shadow-sm border-0 mb-3 bg-white">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="fw-bold text-dark mb-0">{{ __('ui.balance_usage') }}</h6>
                  <span class="text-muted small fw-semibold">{{ number_format($percentage, 0) }}% {{ __('ui.of_initial_deposit') }}</span>
                </div>
                <div class="progress mb-2" style="height: 10px; border-radius: 5px;">
                  <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $wallet->balance }}" aria-valuemin="0" aria-valuemax="{{ $maxRef }}"></div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                  <span>{{ system_currency_format(0) }}</span>
                  <span>{{ system_currency_format($maxRef) }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-lg-4">
            <div class="card shadow-sm border-0 bg-white h-100">
              <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0">Request Withdrawal</h5>
              </div>
              <div class="card-body p-4">
                <form action="{{ route('seller.wallet.withdraw') }}" method="post">
                  @csrf
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Withdrawal Amount ({{ system_currency_symbol() }})</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0.01"
                      max="{{ $wallet->balance }}"
                      name="amount"
                      class="form-control form-control-lg"
                      required
                      placeholder="0.00"
                    >
                    <small class="text-muted d-block mt-2">Maximum available for withdrawal is {{ system_currency_format($wallet->balance) }}.</small>
                  </div>
                  <div class="mb-2">
                    <small class="text-muted d-block">Payouts will be processed to your profile IBAN code:</small>
                    <strong class="d-block text-dark">{{ Auth::user()->iban_code ?: 'No IBAN Code (Set in Settings)' }}</strong>
                  </div>
                  @if (!Auth::user()->iban_code)
                    <div class="alert alert-warning p-2 small mt-2">
                      Please configure your IBAN code in <a href="{{ route('seller.settings') }}">Settings</a> before submitting.
                    </div>
                  @endif
                  <button
                    type="submit"
                    class="btn btn-warning text-white w-100 btn-lg mt-3"
                    {{ (!Auth::user()->iban_code || $wallet->balance <= 0) ? 'disabled' : '' }}
                  >
                    Request Payout
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <!-- Withdrawal Requests History -->
          <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white">
              <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0">Withdrawal History</h5>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="withdrawalsTable" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Requested</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3">Notes</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($withdrawals as $w)
                        @php
                          $badgeClass = 'bg-secondary';
                          if ($w->status === 'pending') {
                              $badgeClass = 'bg-light text-warning';
                          } elseif ($w->status === 'approved') {
                              $badgeClass = 'bg-light text-success';
                          } elseif ($w->status === 'rejected') {
                              $badgeClass = 'bg-light text-danger';
                          }
                        @endphp
                        <tr>
                          <td class="px-4 py-3 fw-semibold">#{{ $w->id }}</td>
                          <td class="py-3 fw-bold">{{ system_currency_format($w->amount) }}</td>
                          <td class="py-3 text-muted small">{{ $w->created_at->format('d/m Y H:i') }}</td>
                          <td class="py-3"><span class="badge {{ $badgeClass }} border px-2 py-1">{{ ucfirst($w->status) }}</span></td>
                          <td class="px-4 py-3 text-muted small">{{ $w->admin_notes ?: '—' }}</td>
                        </tr>
                      @empty
                        <tr class="empty-row">
                          <td colspan="5" class="text-center py-4 text-muted">No withdrawal requests submitted yet.</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Logs -->
          <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white">
              <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0">{{ __('ui.recent_movements') }}</h5>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="transactionsTable" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="px-4 py-3">{{ __('ui.date') }}</th>
                        <th class="py-3">{{ __('ui.description') }}</th>
                        <th class="py-3">{{ __('ui.type') }}</th>
                        <th class="text-end px-4 py-3">{{ __('ui.amount') }}</th>
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
                          </td>
                          <td class="py-3"><span class="badge {{ $badgeClass }} border px-2 py-1">{{ $typeName }}</span></td>
                          <td class="text-end px-4 py-3 fw-bold {{ $tx->amount < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $tx->amount < 0 ? '-' : '+' }}{{ system_currency_format(abs($tx->amount)) }}
                          </td>
                        </tr>
                      @empty
                        <tr class="empty-row">
                          <td colspan="4" class="text-center py-4 text-muted">{{ __('ui.no_transactions_recorded_yet') }}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Topup Wallet Modal -->
<div class="modal fade" id="topupWalletModal" tabindex="-1" aria-labelledby="topupWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h5 class="modal-title fw-semibold" id="topupWalletModalLabel">{{ __('ui.top_up_add_balance') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form action="{{ route('seller.wallet.topup') }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <p class="text-muted">Add money to your wallet to be able to pay order commissions.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount to Add ({{ system_currency_symbol() }})</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-lg" required placeholder="0.00">
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.top_up_add_balance') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    if ($('#withdrawalsTable').length && !$('#withdrawalsTable tbody tr.empty-row').length) {
      $('#withdrawalsTable').DataTable({
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
        "iDisplayLength": 5,
        "order": [[ 0, "desc" ]]
      });
    }

    if ($('#transactionsTable').length && !$('#transactionsTable tbody tr.empty-row').length) {
      $('#transactionsTable').DataTable({
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
        "iDisplayLength": 5,
        "order": []
      });
    }

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
