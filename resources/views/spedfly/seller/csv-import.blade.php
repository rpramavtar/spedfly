@include('spedfly.include.header')

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>{{ __('ui.csv_import') }}</h4>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <a
                    class="btn btn-outline-success fw-semibold"
                    href="{{ route('seller.csv-import.shopify-sample') }}"
                >
                    <i class="bi bi-file-earmark-arrow-down me-2"></i>Shopify Sample CSV
                </a>
                <a
                    class="btn btn-outline-warning fw-semibold"
                    href="{{ route('seller.csv-import.template') }}"
                >
                    <i class="bi bi-file-earmark-arrow-down me-2"></i>{{ __('ui.demo_format') }}
                </a>
                <a class="btn btn-primary" href="{{ route('seller.dashboard') }}"><i class="bi bi-arrow-left me-2"></i> {{ __('ui.back_to_dashboard') }}</a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning js-auto-hide-alert">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- ================= STEP 1: UPLOAD ================= -->
        <div class="card card-shadow p-4 mb-4">
            <h6 class="section-title">{{ __('ui.step_1_upload_csv') }}</h6>
            <form method="post" action="{{ route('seller.csv-import.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="upload-box">
                    <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                    <h5 class="mt-3">{{ __('ui.upload_your_csv') }}</h5>
                    <p class="text-muted">{{ __('ui.choose_file_process_validation') }}</p>
                    <input name="csv_file" accept=".csv,.txt" class="form-control mt-3" type="file" required>
                    <small class="text-muted d-block mt-2">{{ __('ui.supported_format_max_size', ['format' => '.csv', 'size' => '10MB']) }}</small>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-2"></i>{{ __('ui.process_csv') }}</button>
                </div>
            </form>
        </div>

        @php
            $resultData = $result ?? null;
            $mappings = $resultData['mappings'] ?? [];
            $previewRows = $resultData['preview'] ?? [];
            $errorLog = $resultData['error_log'] ?? [];
            $summary = $resultData['summary'] ?? ['total' => 0, 'valid' => 0, 'errors' => 0, 'success_rate' => 0];
        @endphp

        <!-- ================= STEP 2: COLUMN MAPPING ================= -->
        <div class="card card-shadow p-4 mb-4">
            <h6 class="section-title">{{ __('ui.step_2_column_mapping') }}</h6>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.csv_column') }}</th>
                            <th>{{ __('ui.system_field') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $column => $mappedField)
                            <tr>
                                <td>{{ $column }}</td>
                                <td>
                                    <select class="form-select rounded-pill" disabled>
                                        <option>{{ $mappedField ? ucwords(str_replace('_', ' ', $mappedField)) : __('ui.not_mapped') }}</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted">{{ __('ui.upload_csv_view_mappings') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================= STEP 3: VALIDATION PREVIEW ================= -->
        <div class="card card-shadow p-4 mb-4">
            <h6 class="section-title">{{ __('ui.step_3_validation_preview') }}</h6>
            <div class="table-responsive">
                <table id="csvPreviewTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('ui.order_id') }}</th>
                            <th>{{ __('ui.customer') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($previewRows as $row)
                            <tr>
                                <td>{{ $row['row_no'] }}</td>
                                <td>{{ $row['order_id'] !== '' ? $row['order_id'] : '-' }}</td>
                                <td>{{ $row['customer'] !== '' ? $row['customer'] : '-' }}</td>
                                <td>
                                    <span class="badge {{ $row['status'] === 'Valid' ? 'bg-success' : 'bg-danger' }}">
                                        {{ $row['status'] === 'Valid' ? __('ui.valid') : $row['status'] }}
                                    </span>
                                </td>
                                <td>{{ $row['error'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted">-</td>
                                <td class="text-center text-muted">-</td>
                                <td class="text-center text-muted">-</td>
                                <td class="text-center text-muted">-</td>
                                <td class="text-center text-muted">{{ __('ui.upload_csv_see_preview') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================= ERROR LOG PANEL ================= -->
        <div class="card card-shadow p-4 mb-4">
            <h6 class="section-title">{{ __('ui.error_log') }}</h6>
            @if (count($errorLog) > 0)
                <div class="alert alert-danger rounded-3 mb-0">
                    @foreach ($errorLog as $log)
                        {{ $log }}<br>
                    @endforeach
                </div>
            @else
                <div class="alert alert-success rounded-3 mb-0">{{ __('ui.no_validation_errors_found') }}</div>
            @endif
        </div>

        <!-- ================= IMPORT SUMMARY ================= -->
        <div class="card card-shadow p-4 mb-4">
            <h6 class="section-title">{{ __('ui.import_summary') }}</h6>
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="card card-shadow p-3">
                        <h5 class="fw-bold">{{ $summary['total'] }}</h5><small class="text-muted">{{ __('ui.total_rows') }}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-shadow p-3">
                        <h5 class="fw-bold text-success">{{ $summary['valid'] }}</h5><small class="text-muted">{{ __('ui.valid') }}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-shadow p-3">
                        <h5 class="fw-bold text-danger">{{ $summary['errors'] }}</h5><small class="text-muted">{{ __('ui.errors') }}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-shadow p-3">
                        <h5 class="fw-bold text-primary">{{ $summary['success_rate'] }}%</h5><small class="text-muted">{{ __('ui.success_rate') }}</small>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">{{ __('ui.import_progress') }}</label>
                <div class="progress rounded-pill">
                    <div class="progress-bar bg-primary" style="width: {{ $summary['success_rate'] }}%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    $('#csvPreviewTable').DataTable({
      aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, {{ json_encode(__('ui.all')) }}]],
      iDisplayLength: 5
    });

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 5000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
