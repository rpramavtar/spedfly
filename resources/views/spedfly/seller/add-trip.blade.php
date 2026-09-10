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
                      <h5 class="card-title">Add Trip</h5>
                    </div>
                    <div class="card-body">

                      <!-- Row start -->
                      <div class="row gx-3">
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Upload Image</label>
                            <input type="file" class="form-control" placeholder="Enter fullname">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Trip Id</label>
                            <input type="text" class="form-control" placeholder="Enter Trip Id">
                          </div>
                        </div>
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Driver Name</label>
                            <input type="text" class="form-control" placeholder="Enter Name">
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
                            <label class="form-label">Trip From</label>
                            <input type="text" class="form-control" placeholder="Trip from">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Trip To</label>
                            <input type="text" class="form-control" placeholder="Trip to">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" placeholder="date">
                          </div>
                        </div>
                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" placeholder="Time">
                          </div>
                        </div>

                        <div class="col-lg-6 col-sm-4 col-12">
                          <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control" placeholder="Price">
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
