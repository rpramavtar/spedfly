@include('spedfly.include.header')

<div class="app-body">

            <!-- Container starts -->
            <div class="container-fluid">

              <!-- KPI Cards Row start -->
              <div class="row">
                <div class="col-xl-3 col-sm-6 col-12">
                  <div class="card mb-4">
                    <div class="card-body">
                      <div class="d-flex flex-row align-items-center">
                        <div class="icon-box lg rounded-3 bg-light mb-4">
                          <i class="bi bi-person-badge text-primary fs-2"></i>
                        </div>
                        <div class="ms-4">
                          <h4 class="fw-bold mb-2">12</h4>
                          <h6 class="m-0 fw-normal opacity-50">{{ __('ui.total_admins') }}</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                  <div class="card mb-4">
                    <div class="card-body">
                      <div class="d-flex flex-row align-items-center">
                        <div class="icon-box lg rounded-3 bg-light mb-4">
                          <i class="bi bi-check-circle text-success fs-2"></i>
                        </div>
                        <div class="ms-4">
                          <h4 class="fw-bold mb-2">10</h4>
                          <h6 class="m-0 fw-normal opacity-50">{{ __('ui.active_admins') }}</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                  <div class="card mb-4">
                    <div class="card-body">
                      <div class="d-flex flex-row align-items-center">
                        <div class="icon-box lg rounded-3 bg-light mb-4">
                          <i class="bi bi-clock-history text-warning fs-2"></i>
                        </div>
                        <div class="ms-4">
                          <h4 class="fw-bold mb-2">2</h4>
                          <h6 class="m-0 fw-normal opacity-50">{{ __('ui.inactive_admins') }}</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                  <div class="card mb-4">
                    <div class="card-body">
                      <div class="d-flex flex-row align-items-center">
                        <div class="icon-box lg rounded-3 bg-light mb-4">
                          <i class="bi bi-key text-info fs-2"></i>
                        </div>
                        <div class="ms-4">
                          <h4 class="fw-bold mb-2">1</h4>
                          <h6 class="m-0 fw-normal opacity-50">{{ __('ui.super_admins') }}</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- KPI Cards Row end -->

              <!-- Admin Management Form Row start -->
              <div class="row">
                <div class="col-12">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h4 class="card-title">{{ __('ui.add_new_admin') }}</h4>
                    </div>
                    <div class="card-body">
                      <form class="row g-3">
                        <div class="col-md-4">
                          <label for="adminName" class="form-label">{{ __('ui.full_name') }}</label>
                          <input type="text" class="form-control" id="adminName" placeholder="{{ __('ui.enter_admin_name') }}">
                        </div>
                        <div class="col-md-4">
                          <label for="adminEmail" class="form-label">{{ __('ui.email_address') }}</label>
                          <input type="email" class="form-control" id="adminEmail" placeholder="admin@example.com">
                        </div>
                        <div class="col-md-4">
                          <label for="adminRole" class="form-label">{{ __('ui.role') }}</label>
                          <select class="form-select" id="adminRole">
                            <option selected>{{ __('ui.select_role') }}</option>
                            <option>{{ __('ui.super_admin') }}</option>
                            <option>{{ __('ui.admin') }}</option>
                            <option>{{ __('ui.editor') }}</option>
                            <option>{{ __('ui.moderator') }}</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label for="adminPermissions" class="form-label">{{ __('ui.permissions') }}</label>
                          <select class="form-select" id="adminPermissions" multiple>
                            <option selected>{{ __('ui.view_reports') }}</option>
                            <option selected>{{ __('ui.manage_users') }}</option>
                            <option>{{ __('ui.manage_settings') }}</option>
                            <option>{{ __('ui.delete_content') }}</option>
                            <option>{{ __('ui.export_data') }}</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label for="adminStatus" class="form-label">{{ __('ui.status') }}</label>
                          <select class="form-select" id="adminStatus">
                            <option selected>{{ __('ui.active') }}</option>
                            <option>{{ __('ui.inactive') }}</option>
                            <option>{{ __('ui.suspended') }}</option>
                          </select>
                        </div>
                        <div class="col-12">
                          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>{{ __('ui.add_admin') }}</button>
                          <button type="reset" class="btn btn-outline-secondary ms-2">{{ __('ui.reset') }}</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Admin Management Form Row end -->

              <!-- Admins Table Row start -->
              <div class="row">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header">
                      <h4 class="card-title">{{ __('ui.all_admins') }}</h4>
                    </div>
                    <div class="card-body">
                      <table id="adminsTable" class="table table-bordered">
                        <thead>
                          <tr>
                            <th>{{ __('ui.id') }}</th>
                            <th>{{ __('ui.admin_name') }}</th>
                            <th>{{ __('ui.email') }}</th>
                            <th>{{ __('ui.role') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.last_login') }}</th>
                            <th>{{ __('ui.actions') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>#A001</td>
                            <td>Ella Lindsey</td>
                            <td>ella@spedfly.com</td>
                            <td><span class="badge bg-danger">{{ __('ui.super_admin') }}</span></td>
                            <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                            <td>{{ __('ui.just_now') }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A002</td>
                            <td>John Doe</td>
                            <td>john@spedfly.com</td>
                            <td><span class="badge bg-primary">{{ __('ui.admin') }}</span></td>
                            <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                            <td>{{ __('ui.hours_ago', ['count' => 2]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A003</td>
                            <td>Jane Smith</td>
                            <td>jane@spedfly.com</td>
                            <td><span class="badge bg-info">{{ __('ui.editor') }}</span></td>
                            <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                            <td>{{ __('ui.hours_ago', ['count' => 5]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A004</td>
                            <td>Mark Wilson</td>
                            <td>mark@spedfly.com</td>
                            <td><span class="badge bg-secondary">{{ __('ui.moderator') }}</span></td>
                            <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                            <td>{{ __('ui.days_ago', ['count' => 1]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A005</td>
                            <td>Sarah Johnson</td>
                            <td>sarah@spedfly.com</td>
                            <td><span class="badge bg-primary">{{ __('ui.admin') }}</span></td>
                            <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                            <td>{{ __('ui.days_ago', ['count' => 2]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A006</td>
                            <td>Robert Brown</td>
                            <td>robert@spedfly.com</td>
                            <td><span class="badge bg-info">{{ __('ui.editor') }}</span></td>
                            <td><span class="badge bg-warning text-dark">{{ __('ui.inactive') }}</span></td>
                            <td>{{ __('ui.days_ago', ['count' => 7]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                          <tr>
                            <td>#A007</td>
                            <td>Emily Davis</td>
                            <td>emily@spedfly.com</td>
                            <td><span class="badge bg-secondary">{{ __('ui.moderator') }}</span></td>
                            <td><span class="badge bg-danger">{{ __('ui.suspended') }}</span></td>
                            <td>{{ __('ui.days_ago', ['count' => 15]) }}</td>
                            <td>
                              <button class="btn btn-sm btn-info"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Admins Table Row end -->

            </div>
            <!-- Container ends -->

          </div>
          <!-- App body ends -->

<script>
      $(document).ready(function() {
        $('#adminsTable').DataTable({
          "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
          "iDisplayLength": 5
        });
      });
    </script>

@include('spedfly.include.footer')
