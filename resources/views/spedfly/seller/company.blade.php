@include('spedfly.include.header')

<div class="app-body">

            <!-- Container starts -->
            <div class="container-fluid">

              <!-- Row start -->
              <div class="row">
                <div class="col-12">
                  <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                      <h4 class="card-title">Companies  </h4>
                      <a href="{{ route('seller.add-company') }}" class="btn btn-primary">Add Company</a>
                    </div>
                    <div class="card-body">
                      
                        <div class="table-responsive">
                          <table id="example" class="table table-bordered">
                            <thead>
                              <tr>
                                <th>Company logo</th>
                                <th>Company Name</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Address</th>
                                <th>Status</th>
                               
                                <th style="width:90px;">Actions</th>
                                
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td align="center"><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png class="img-4x rounded-3"></td>
                                
                                <td>XYZ Pvt Ltd</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                               
                                <td>363 Linda St.
                                  Avon Lake, OH 44012</td>
                                
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                               
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                
                                <td>ABC Pvt. Ltd.</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                                
                                <td>5 Del Monte Lane
                                  West Hempstead, NY 11552</td>
                               
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                
                                <td>LMN Pvt Ltd</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                                
                                <td>8 York Drive
                                  Iowa City, IA 52240</td>

                                
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                
                                <td>PQR Pvt Ltd</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                                
                                <td>467 Harrison St.
                                  Port Saint Lucie, FL 34952</td>
                               
                                
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                
                                <td>Lorem Uspsum Pvt Ltd</td>

                                <td>info@example.com</td>
                                <td>479797898XX</td>
                               
                                <td>7 South Courtland St.
                                  Grandville, MI 49418</td>
                               
                               
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                 
                                <td>Car Taxi Pvt Ltd</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                                
                                <td>5 Bow Ridge Road
                                  New Brunswick, NJ 08901</td>

                                
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              <tr>
                                <td><img src="https://toppng.com/uploads/preview/taxi-logos-logo-png-image-116615080155epwqephfv.png" alt="img" class="img-4x rounded-3"></td>
                                
                                <td>Rideservice Pvt Ltd</td>
                                <td>info@example.com</td>
                                <td>479797898XX</td>
                                
                               
                                <td>949 N. Essex Drive
                                  Osseo, MN 55311</td>
                                
                                
                                
                                <td><span class="badge bg-primary"> Active</span></td>
                                <td>
                                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-primary"
                                    data-bs-title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <button class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                    data-bs-placement="top" data-bs-custom-class="custom-tooltip-danger"
                                    data-bs-title="Delete">
                                    <i class="bi bi-trash3"></i>
                                  </button>
                                </td>
                              </tr>
                              
                            </tbody>
                          </table>
                        </div>
                     
                    </div>
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
