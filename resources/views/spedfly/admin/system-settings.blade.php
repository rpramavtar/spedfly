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
                        <h4 class="card-title">{{ __('ui.system_configuration') }}</h4>
                    </div>
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <strong>{{ __('ui.fix_highlighted_settings') }}</strong>
                            </div>
                        @endif

                        <form class="row g-3" method="POST" action="{{ route('admin.system-settings.update') }}">
                            @csrf

                            @foreach ($settingDefinitions as $key => $definition)
                                @php
                                    $currentValue = old($key, $settings[$key] ?? $definition['default']);
                                @endphp

                                @if ($definition['type'] === 'boolean')
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input class="form-check-input @error($key) is-invalid @enderror" type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" {{ $currentValue ? 'checked' : '' }}>
                                            <label class="form-check-label" for="{{ $key }}">
                                                {{ __('ui.' . strtolower($definition['label_key'] ?? $definition['label'])) }}
                                            </label>
                                            @error($key)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @elseif ($definition['type'] === 'select')
                                    <div class="col-md-6">
                                        <label for="{{ $key }}" class="form-label">{{ __('ui.' . ($definition['label_key'] ?? 'system_configuration')) }}</label>
                                        <select class="form-select @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}">
                                            @foreach ($definition['options'] as $optionValue => $optionLabel)
                                                @php
                                                    $displayLabel = $key === 'currency'
                                                        ? match ($optionValue) {
                                                            'INR' => 'INR (₹)',
                                                            'USD' => 'USD ($)',
                                                            'EUR' => 'EUR (€)',
                                                            'GBP' => 'GBP (£)',
                                                            default => $optionLabel,
                                                        }
                                                        : $optionLabel;
                                                @endphp
                                                <option value="{{ $optionValue }}" {{ (string) $currentValue === (string) $optionValue ? 'selected' : '' }}>
                                                    {{ $displayLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error($key)
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @else
                                    <div class="col-md-6">
                                        <label for="{{ $key }}" class="form-label">{{ __('ui.' . ($definition['label_key'] ?? 'system_configuration')) }}</label>
                                        <input
                                            type="{{ $definition['type'] === 'number' ? 'number' : 'text' }}"
                                            class="form-control @error($key) is-invalid @enderror"
                                            id="{{ $key }}"
                                            name="{{ $key }}"
                                            value="{{ $currentValue }}"
                                            @if (isset($definition['min'])) min="{{ $definition['min'] }}" @endif
                                        >
                                        @error($key)
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            @endforeach

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>{{ __('ui.save_settings') }}
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">{{ __('ui.reset') }}</button>
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
                        <h4 class="card-title">{{ __('ui.configuration_history') }}</h4>
                    </div>
                    <div class="card-body">
                        <table id="settingsConfigTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.id') }}</th>
                                    <th>{{ __('ui.setting_name') }}</th>
                                    <th>{{ __('ui.previous_value') }}</th>
                                    <th>{{ __('ui.new_value') }}</th>
                                    <th>{{ __('ui.changed_by') }}</th>
                                    <th>{{ __('ui.changed_on') }}</th>
                                    <th>{{ __('ui.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($historyRows as $row)
                                    <tr>
                                        <td>#S{{ str_pad((string) $row->id, 3, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $row->setting_label }}</td>
                                        <td>{{ $row->previous_value ?? '-' }}</td>
                                        <td>{{ $row->new_value ?? '-' }}</td>
                                        <td>{{ $row->changedBy?->name ?? __('ui.system') }}</td>
                                        <td>{{ optional($row->created_at)->format('d M Y') ?? '-' }}</td>
                                        <td><span class="badge bg-success">{{ ucfirst($row->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('ui.no_configuration_changes') }}</td>
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

<script>
    $(document).ready(function() {
        $('#settingsConfigTable').DataTable({
            "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
            "iDisplayLength": 5
        });
    });
</script>

@include('spedfly.include.footer')
