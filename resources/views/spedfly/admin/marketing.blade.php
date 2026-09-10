@include('spedfly.include.header')

<div class="app-body">

            <div class="container-fluid">

              <!-- Page title -->
              <div class="row mb-3">
                <div class="col-12">
                  <div class="d-flex align-items-center justify-content-between">
                  <div>
                  <h1 class="mt-4">{{ __('ui.marketing_campaigns') }}</h1>
                  <p class="text-muted">{{ __('ui.manage_email_campaigns_social_media_posts_and_promotional_activities') }}</p>
                  </div>
                  <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCampaignModal">
                   <i class="bi bi-plus-circle"></i> {{ __('ui.add_campaign') }}
                    </button>
                  </div>
                </div>
                </div>
              </div>

              <!-- KPI Cards Row 1 -->
              <div class="row mb-3">
                <div class="col-md-3">
                  <div class="card bg-primary text-white">
                    <div class="card-body">
                      <h6 class="card-title">{{ __('ui.active_campaigns') }}</h6>
                      <h2 class="mb-0">24</h2>
                      <small>{{ __('ui.running_now') }}</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card bg-success text-white">
                    <div class="card-body">
                      <h6 class="card-title">{{ __('ui.email_sent') }}</h6>
                      <h2 class="mb-0">125.3K</h2>
                      <small>{{ __('ui.this_month') }}</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card bg-info text-white">
                    <div class="card-body">
                      <h6 class="card-title">{{ __('ui.conversion_rate') }}</h6>
                      <h2 class="mb-0">8.5%</h2>
                      <small>{{ __('ui.avg_performance') }}</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card bg-warning text-white">
                    <div class="card-body">
                      <h6 class="card-title">{{ __('ui.social_engagement') }}</h6>
                      <h2 class="mb-0">45.2K</h2>
                      <small>{{ __('ui.likes_and_shares') }}</small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Filter Form Card -->
              <div class="card mb-3">
                <div class="card-body">
                  <form class="row g-2">
                    <div class="col-md-3"><input type="text" class="form-control" placeholder="{{ __('ui.search_email_title') }}"></div>
                    <div class="col-md-3">
                      <select class="form-select">
                        <option value="">{{ __('ui.all_types') }}</option>
                        <option value="email">{{ __('ui.email') }}</option>
                        <option value="social">{{ __('ui.social_media') }}</option>
                        <option value="sms">{{ __('ui.sms') }}</option>
                        <option value="push">{{ __('ui.push_notification') }}</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <select class="form-select">
                        <option value="">{{ __('ui.all_statuses') }}</option>
                        <option value="active">{{ __('ui.active') }}</option>
                        <option value="scheduled">{{ __('ui.scheduled') }}</option>
                        <option value="completed">{{ __('ui.completed') }}</option>
                        <option value="draft">{{ __('ui.draft') }}</option>
                      </select>
                    </div>
                    <div class="col-md-3 text-end"><button type="button" class="btn btn-primary w-100">{{ __('ui.filter') }}</button></div>
                  </form>
                </div>
              </div>

              <!-- Campaigns Table -->
              <div class="card">
                 <div class="card-body">
                <div class="table-responsive">
                  <table id="campaignsTable" class="table table-bordered table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th><input type="checkbox" class="form-check-input"></th>
                        <th>{{ __('ui.campaign_name') }}</th>
                        <th>{{ __('ui.type') }}</th>
                        <th>{{ __('ui.created_by') }}</th>
                        <th>{{ __('ui.start_date') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th>{{ __('ui.recipients') }}</th>
                        <th>{{ __('ui.click_rate') }}</th>
                        <th>{{ __('ui.actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Summer Sale 2026</strong></td>
                        <td><span class="badge bg-info">{{ __('ui.email') }}</span></td>
                        <td>Sarah Johnson</td>
                        <td>2026-02-01</td>
                        <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                        <td>45,234</td>
                        <td>12.5%</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Valentine's Day Promo</strong></td>
                        <td><span class="badge bg-warning">{{ __('ui.social_media') }}</span></td>
                        <td>Marco Rossi</td>
                        <td>2026-02-05</td>
                        <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                        <td>78,900</td>
                        <td>18.3%</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Flash Deal Weekend</strong></td>
                        <td><span class="badge bg-danger">{{ __('ui.sms') }}</span></td>
                        <td>Lisa Chen</td>
                        <td>2026-02-08</td>
                        <td><span class="badge bg-secondary">{{ __('ui.scheduled') }}</span></td>
                        <td>23,456</td>
                        <td>-</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Customer Loyalty Program</strong></td>
                        <td><span class="badge bg-info">{{ __('ui.email') }}</span></td>
                        <td>Ahmed Hassan</td>
                        <td>2026-01-15</td>
                        <td><span class="badge bg-primary">{{ __('ui.completed') }}</span></td>
                        <td>156,789</td>
                        <td>9.8%</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>New Year Clearance</strong></td>
                        <td><span class="badge bg-secondary">{{ __('ui.push_notification') }}</span></td>
                        <td>Emma Watson</td>
                        <td>2026-01-10</td>
                        <td><span class="badge bg-primary">{{ __('ui.completed') }}</span></td>
                        <td>234,567</td>
                        <td>14.2%</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Seasonal Collection Drop</strong></td>
                        <td><span class="badge bg-warning">{{ __('ui.social_media') }}</span></td>
                        <td>David Lee</td>
                        <td>2026-02-03</td>
                        <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                        <td>89,123</td>
                        <td>16.7%</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                      <tr>
                        <td><input type="checkbox" class="form-check-input"></td>
                        <td><strong>Referral Bonus Campaign</strong></td>
                        <td><span class="badge bg-info">{{ __('ui.email') }}</span></td>
                        <td>Jessica Brown</td>
                        <td>2026-02-06</td>
                        <td><span class="badge bg-light text-dark">{{ __('ui.draft') }}</span></td>
                        <td>-</td>
                        <td>-</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">{{ __('ui.edit') }}</button>
                          <button class="btn btn-sm btn-outline-danger">{{ __('ui.delete') }}</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              </div>

            </div>

          </div>
          <!-- App body ends -->

<script>
      $(document).ready(function() {
    $('#campaignsTable').DataTable(
         {     
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, @json(__('ui.all'))]],
        "iDisplayLength": 5
       } 
        );
   } );
   </script>

@include('spedfly.include.footer')
