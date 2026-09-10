<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="light"  data-sidebar-size="lg" data-sidebar-image="none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Panel</title>
    <link rel="shortcut icon" href="{{ asset('assets/admin/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/plugins/fontawesome/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/plugins/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/plugins/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap-datetimepicker.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.1/css/dataTables.dataTables.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.dataTables.css">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/style.css') }}">
    <script src="{{ asset('assets/admin/js/layout.js') }}"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

  <body>
<div class="main-wrapper">
    <div class="header header-one">
        <a href="{{ route('admin.admin') }}" class="d-inline-flex d-sm-inline-flex align-items-center d-md-inline-flex d-lg-none align-items-center device-logo"><img src="{{ asset('assets/admin/img/logo.png') }}" class="img-fluid logo2" alt="Logo" style="width:60px"></a>
        <div class="main-logo d-inline float-start d-lg-flex align-items-center d-none d-sm-none d-md-none"><div class="logo-color"><a href="{{ route('admin.admin') }}"><img src="{{ asset('assets/admin/img/logo.png') }}" class="img-fluid logo-blue" alt="Logo" style="width:200px"></a></div></div>
        <a href="javascript:void(0);" id="toggle_btn"><span class="toggle-bars"><span class="bar-icons"></span><span class="bar-icons"></span><span class="bar-icons"></span><span class="bar-icons"></span></span></a>
        <div class="top-nav-search"><form><input type="text" class="form-control" placeholder="Search admin..."><button class="btn" type="submit"><img src="{{ asset('assets/admin/img/icons/search.svg') }}" alt="img"></button></form></div>
        <a class="mobile_btn" id="mobile_btn"><i class="fas fa-bars"></i></a>
        <ul class="nav nav-tabs user-menu"><li class="nav-item dropdown"><a href="javascript:void(0)" class="user-link nav-link" data-bs-toggle="dropdown"><span class="user-img"><img src="{{ asset('assets/admin/img/profiles/avatar-07.jpg') }}" alt="img" class="profilesidebar"><span class="animate-circle"></span></span><span class="user-content"><span class="user-details">Super Admin</span><span class="user-name">John</span></span></a><div class="dropdown-menu menu-drop-user"><div class="profilemenu"><div class="subscription-logout"><ul><li class="pb-0"><a class="dropdown-item" href="{{ route('admin.login') }}">Log Out</a></li></ul></div></div></div></li></ul>
    </div>
    <div class="sidebar" id="sidebar"><div class="sidebar-inner slimscroll"><div id="sidebar-menu" class="sidebar-menu"><ul class="sidebar-vertical"><li><a href="{{ route('admin.admin') }}" class="active"><i class="bi bi-people"></i> Sellers</a></li><li><a href="#"><i class="bi bi-card-checklist"></i> All Orders</a></li><li><a href="#"><i class="bi bi-bar-chart-line"></i> Global Stats</a></li><li><a href="#"><i class="bi bi-bill"></i> Billing</a></li><li><a href="#"><i class="bi bi-file-earmark-text"></i> System Logs</a></li></ul></div></div></div>
    <div class="page-wrapper"><div class="content container-fluid">
        <div class="page-header fade-in"><div class="row align-items-center"><div class="col"><h1 class="page-title">Super Admin</h1><p class="text-muted">Overview of all sellers and system</p></div></div></div>
        <div class="row g-3 mb-4"><div class="col-md-4"><div class="card p-4"><h6>Total Sellers</h6><h3>128</h3></div></div><div class="col-md-4"><div class="card p-4"><h6>Global Orders</h6><h3>54,230</h3></div></div><div class="col-md-4"><div class="card p-4"><h6>Revenue</h6><h3>$1.2M</h3></div></div></div>
        <div class="card p-4"><h6>Seller List</h6><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Seller</th><th>Orders</th><th>Revenue</th></tr></thead><tbody><tr><td>Seller A</td><td>4,200</td><td>$120k</td></tr><tr><td>Seller B</td><td>3,800</td><td>$98k</td></tr></tbody></table></div></div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/admin/js/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('assets/admin/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/admin/js/feather.min.js') }}"></script>
<script src="{{ asset('assets/admin/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>
<script src="{{ asset('assets/admin/js/theme-settings.js') }}"></script>
<script src="{{ asset('assets/admin/js/greedynav.js') }}"></script>
<script src="{{ asset('assets/admin/js/script.js') }}"></script>
</body>
</html>





