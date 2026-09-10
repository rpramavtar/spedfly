@include('spedfly.include.header')

<div class="app-body">

            <!-- Container starts -->
            <div class="container-fluid">

              <!-- Row start -->
              <div class="row">
                <div class="col-12">
                 <div class="card mb-3">
                  <form>
                    <div class="card-header">
                      <h5 class="card-title">Add Driver</h5>
                    </div>
                    <div class="card-body">

                      <!-- Row start -->
                      <div class="row gx-3">
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Upload Driver Image</label>
                            <input type="file" class="form-control" placeholder="Enter fullname">
                          </div>
                        </div>

                       
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Driver Name</label>
                            <input type="text" class="form-control" placeholder="Enter fullname">
                          </div>
                        </div>
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" placeholder="Enter email address">
                          </div>
                        </div>
                        
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" placeholder="Phone">
                          </div>
                        </div>
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" placeholder="License">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" placeholder="Address">
                          </div>
                        </div>

                       
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Company</label>
                            <select class="form-control" >
                              <option>Select Company</option>
                              <option>XYZ Pvt Ltd</option>
                              <option>PQR Pvt Ltd</option>
                              <option>LMN Pvt Ltd</option>
                              <option>ABC Pvt Ltd</option>

                            </select>
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" >
                              <option>Select Status</option>
                              <option>Active</option>
                              <option>Inactice</option>
                              

                            </select>
                          </div>
                        </div>
                       
                        
                        
                      </div>
                      <!-- Row end -->
                    </div>
                    <div class="card-footer">
                      <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary">
                          Cancel
                        </button>
                        <button type="button" class="btn btn-primary">
                          Submit
                        </button>
                      </div>
                    </div>
                  </form>
                  </div>
                </div>
              </div>
              <!-- Row end -->

            </div>
            <!-- Container ends -->

          </div>
          <!-- App body ends -->

<script>
      $(document).ready(function() {
    $('#example').DataTable(
         {     
      "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
        "iDisplayLength": 5
       } 
        );
   } );
    </script>

@include('spedfly.include.footer')
