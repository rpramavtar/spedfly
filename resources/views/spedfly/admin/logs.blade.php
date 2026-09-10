@include('spedfly.include.header')

<div class="app-body">
    <div class="container-fluid">
        <div class="row">
            @foreach ($summaryCards as $card)
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-row align-items-center">
                                <div class="icon-box lg rounded-3 bg-light mb-4">
                                    <i class="bi {{ $card['icon'] }} text-{{ $card['color'] }} fs-2"></i>
                                </div>
                                <div class="ms-4">
                                    <h4 class="fw-bold mb-2">{{ $card['value'] }}</h4>
                                    <h6 class="m-0 fw-normal opacity-50">{{ $card['label'] }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('ui.filter_logs') }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="row g-3" method="GET" action="{{ route('admin.logs') }}">
                            <div class="col-md-3">
                                <label for="type" class="form-label">{{ __('ui.log_type') }}</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="all" {{ ($filters['type'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('ui.all') }}</option>
                                    <option value="error" {{ ($filters['type'] ?? 'all') === 'error' ? 'selected' : '' }}>{{ __('ui.error') }}</option>
                                    <option value="warning" {{ ($filters['type'] ?? 'all') === 'warning' ? 'selected' : '' }}>{{ __('ui.warning') }}</option>
                                    <option value="success" {{ ($filters['type'] ?? 'all') === 'success' ? 'selected' : '' }}>{{ __('ui.info') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="module" class="form-label">{{ __('ui.module') }}</label>
                                <select class="form-select" id="module" name="module">
                                    <option value="all" {{ ($filters['module'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('ui.all_modules') }}</option>
                                    @foreach ($moduleOptions as $value => $label)
                                        <option value="{{ $value }}" {{ ($filters['module'] ?? 'all') === (string) $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">{{ __('ui.date') }}</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ $filters['date'] ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label for="user" class="form-label">{{ __('ui.user') }}</label>
                                <input type="text" class="form-control" id="user" name="user" placeholder="{{ __('ui.search_by_user') }}" value="{{ $filters['user'] ?? '' }}">
                            </div>
                            <div class="col-12">
                                <label for="search" class="form-label">{{ __('ui.keyword_search') }}</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="{{ __('ui.search_title_message_or_user') }}" value="{{ $filters['search'] ?? '' }}">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-2"></i>{{ __('ui.search') }}
                                </button>
                                <a href="{{ route('admin.logs') }}" class="btn btn-outline-secondary ms-2">{{ __('ui.reset') }}</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('ui.activity_logs') }}</h4>
                    </div>
                    <div class="card-body">
                        <table id="logsTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.id') }}</th>
                                    <th>{{ __('ui.timestamp') }}</th>
                                    <th>{{ __('ui.type') }}</th>
                                    <th>{{ __('ui.module') }}</th>
                                    <th>{{ __('ui.user') }}</th>
                                    <th>{{ __('ui.message') }}</th>
                                    <th>{{ __('ui.ip_address') }}</th>
                                    <th>{{ __('ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td>#L{{ str_pad((string) $log['id'], 3, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $log['timestamp'] }}</td>
                                        <td><span class="badge {{ $log['type_class'] }}">{{ $log['type'] }}</span></td>
                                        <td>{{ $log['module'] }}</td>
                                        <td>{{ $log['user'] }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($log['message'], 80) }}</td>
                                        <td>{{ $log['ip_address'] }}</td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-info js-log-view"
                                                data-log-title="{{ $log['details']['title'] }}"
                                                data-log-message="{{ $log['details']['message'] }}"
                                                data-log-type="{{ $log['details']['type'] }}"
                                                data-log-module="{{ $log['details']['module'] }}"
                                                data-log-user="{{ $log['details']['user'] }}"
                                                data-log-ip="{{ $log['details']['ip_address'] }}"
                                                data-log-created="{{ $log['details']['created_at'] }}"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">{{ __('ui.no_logs_found_for_selected_filters') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="d-flex justify-content-end mt-3">
                            {{ $logs->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logDetailModalLabel">{{ __('ui.log_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('ui.title') }}</dt>
                    <dd class="col-sm-9" id="logDetailTitle">-</dd>

                    <dt class="col-sm-3">{{ __('ui.type') }}</dt>
                    <dd class="col-sm-9" id="logDetailType">-</dd>

                    <dt class="col-sm-3">{{ __('ui.module') }}</dt>
                    <dd class="col-sm-9" id="logDetailModule">-</dd>

                    <dt class="col-sm-3">{{ __('ui.user') }}</dt>
                    <dd class="col-sm-9" id="logDetailUser">-</dd>

                    <dt class="col-sm-3">{{ __('ui.ip_address') }}</dt>
                    <dd class="col-sm-9" id="logDetailIp">-</dd>

                    <dt class="col-sm-3">{{ __('ui.created_on') }}</dt>
                    <dd class="col-sm-9" id="logDetailCreated">-</dd>

                    <dt class="col-sm-3">{{ __('ui.message') }}</dt>
                    <dd class="col-sm-9" id="logDetailMessage">-</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    $(function () {
        function fillLogModal($button) {
            $('#logDetailTitle').text($button.data('log-title') || '-');
            $('#logDetailType').text($button.data('log-type') || '-');
            $('#logDetailModule').text($button.data('log-module') || '-');
            $('#logDetailUser').text($button.data('log-user') || '-');
            $('#logDetailIp').text($button.data('log-ip') || '-');
            $('#logDetailCreated').text($button.data('log-created') || '-');
            $('#logDetailMessage').text($button.data('log-message') || '-');
        }

        $(document).on('click', '.js-log-view', function () {
            fillLogModal($(this));

            var modalEl = document.getElementById('logDetailModal');
            if (modalEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });
</script>
@endpush

@include('spedfly.include.footer')
