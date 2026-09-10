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
                      <h5 class="card-title">Add Company</h5>
                    </div>
                    <div class="card-body">

                      <!-- Row start -->
                      <div class="row gx-3">
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Upload Company logo</label>
                            <input type="file" class="form-control" placeholder="">
                          </div>
                        </div>

                       
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" placeholder="Company Name">
                          </div>
                        </div>
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" placeholder="Enter email address">
                          </div>
                        </div>
                        
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Telephone Number</label>
                            <input type="text" class="form-control" placeholder="Phone">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">VAT Number</label>
                            <input type="text" class="form-control" placeholder="VAT Number">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">IBAN Code</label>
                            <input type="text" class="form-control" placeholder="IBAN Code">
                          </div>
                        </div>
                        

                        <div class="col-lg-12 col-sm-12 col-12">
                          <div class="mb-3">
                            <label class="form-label">Address</label>

                            <textarea class="form-control" ></textarea>
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
