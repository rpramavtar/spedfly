@include('spedfly.include.header')

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
          <div>
            <h1 class="h3 text-gray-800">{{ __('ui.withdrawal_requests') }}</h1>
            <p class="text-muted">Review, approve, or reject withdrawal requests from merchants.</p>
          </div>
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if (session('error'))
          <div class="alert alert-danger js-auto-hide-alert">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
          <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('admin.withdrawals') }}">
              <div class="col-md-4">
                <select class="form-select" name="status" onchange="this.form.submit()">
                  <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All statuses</option>
                  <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                  <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                  <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
              </div>
            </form>
          </div>
        </div>

        <div class="card shadow-sm border-0 mb-4 bg-white">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table id="withdrawalsTable" class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="py-3">Seller</th>
                    <th class="py-3">Bank Details</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Requested Date</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Admin Notes</th>
                    <th class="text-end px-4 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($withdrawals as $w)
                    @php
                      $statusClass = 'bg-secondary';
                      if ($w->status === 'pending') {
                          $statusClass = 'bg-warning text-dark';
                      } elseif ($w->status === 'approved') {
                          $statusClass = 'bg-success';
                      } elseif ($w->status === 'rejected') {
                          $statusClass = 'bg-danger';
                      }
                    @endphp
                    <tr>
                      <td class="px-4 py-3 fw-semibold">#{{ $w->id }}</td>
                      <td class="py-3">
                        <strong>{{ $w->seller?->name }}</strong><br>
                        <small class="text-muted">{{ $w->seller?->company_name }}</small>
                      </td>
                      <td class="py-3">
                        <small class="d-block"><strong>Holder:</strong> {{ $w->seller?->account_holder ?: ($w->seller?->company_name ?: $w->seller?->name) }}</small>
                        <small class="d-block"><strong>IBAN:</strong> {{ $w->seller?->iban_code ?: '—' }}</small>
                        @if ($w->seller?->bank_name)
                          <small class="d-block"><strong>Bank:</strong> {{ $w->seller?->bank_name }}</small>
                        @endif
                      </td>
                      <td class="py-3 fw-bold">{{ system_currency_format($w->amount) }}</td>
                      <td class="py-3 text-muted small">{{ $w->created_at->format('d/m Y H:i') }}</td>
                      <td class="py-3"><span class="badge {{ $statusClass }} border px-2 py-1">{{ ucfirst($w->status) }}</span></td>
                      <td class="py-3 text-muted small">{{ $w->admin_notes ?: '—' }}</td>
                      <td class="text-end px-4 py-3">
                        @if ($w->status === 'pending')
                          <form action="{{ route('admin.withdrawals.approve', $w) }}" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this withdrawal request?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success me-1">Approve</button>
                          </form>
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-danger reject-withdrawal-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#rejectWithdrawalModal"
                            data-action-url="{{ route('admin.withdrawals.reject', $w) }}"
                            data-amount="{{ system_currency_format($w->amount) }}"
                            data-seller="{{ $w->seller?->name }}"
                          >
                            Reject
                          </button>
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="8" class="text-center py-4 text-muted">No withdrawal requests found.</td>
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

<!-- Reject Withdrawal Modal -->
<div class="modal fade" id="rejectWithdrawalModal" tabindex="-1" aria-labelledby="rejectWithdrawalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h5 class="modal-title fw-semibold" id="rejectWithdrawalModalLabel">Reject Withdrawal Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="rejectWithdrawalForm" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <p class="text-muted">Rejecting withdrawal request of <strong id="rejectAmount"></strong> from <strong id="rejectSeller"></strong>. The funds will be refunded to the seller's wallet balance.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Rejection Reason / Notes</label>
            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Enter reason for rejection..." required></textarea>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger">Reject & Refund</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var hasEmptyRow = $('#withdrawalsTable tbody tr.empty-row').length > 0;
    if (!hasEmptyRow) {
      $('#withdrawalsTable').DataTable({
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
        "iDisplayLength": 10
      });
    }

    $('.reject-withdrawal-btn').on('click', function () {
      var actionUrl = $(this).data('action-url');
      var amount = $(this).data('amount');
      var seller = $(this).data('seller');

      $('#rejectWithdrawalForm').attr('action', actionUrl);
      $('#rejectAmount').text(amount);
      $('#rejectSeller').text(seller);
    });

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
