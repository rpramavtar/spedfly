@php
    $isAdmin = request()->is('admin*');
    $assetBase = $isAdmin ? 'assets/admin' : 'assets/seller';
    $homeRoute = $isAdmin ? route('admin.dashboard') : route('seller.dashboard');
    $authUser = Auth::user();
    $profileName = $authUser?->name ?? ($isAdmin ? 'Admin' : 'Seller');
    $profileEmail = $authUser?->email ?? '';
    $profileInitial = strtoupper(substr($profileName, 0, 1));
    $profileAvatarUrl = $authUser?->avatar_path ? asset($authUser->avatar_path) : null;
    $profileRoute = $isAdmin ? route('admin.profile') : route('seller.profile');
    $settingsRoute = $isAdmin ? route('admin.settings') : route('seller.settings');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Spedfly</title>

    <link rel="shortcut icon" href="{{ asset($assetBase . '/images/favicon.png') }}" />
    <link rel="stylesheet" href="{{ asset($assetBase . '/fonts/bootstrap/bootstrap-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset($assetBase . '/css/main.min.css') }}" />
    <link rel="stylesheet" href="{{ asset($assetBase . '/vendor/overlay-scroll/OverlayScrollbars.min.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            overflow: hidden;
        }
        .app-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-body {
            height: auto !important;
            flex: 1 1 auto;
            overflow: auto;
        }
        .app-brand {
            min-height: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .app-brand .logo {
            max-height: 44px;
            width: auto;
            object-fit: contain;
        }
        .app-header {
            min-height: 72px;
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            overflow: visible;
        }
        .breadcrumb {
            margin-bottom: 0;
            white-space: nowrap;
        }
        .header-actions {
            margin-left: auto;
            flex-shrink: 0;
        }
        .header-actions .dropdown-toggle::after {
            display: none;
        }
        .flag-icon {
            width: 18px;
            height: 12px;
            object-fit: cover;
            border-radius: 2px;
        }
        .dropdown-menu-md {
            min-width: 320px;
        }
        .notification-dropdown-menu {
            max-height: 420px;
            overflow: hidden;
            padding-bottom: 0;
        }
        .notification-dropdown-body {
            max-height: 320px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .dropdown-menu-mini {
            min-width: 240px;
        }
        .dropdown-menu-md::before,
        .dropdown-menu-mini::before {
            content: "";
            position: absolute;
            top: -8px;
            right: 20px;
            width: 14px;
            height: 14px;
            background: #fff;
            border-left: 1px solid #dbe3ea;
            border-top: 1px solid #dbe3ea;
            transform: rotate(45deg);
        }
        .msg-avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
        }
        .profile-avatar-lg {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-avatar-image {
            display: inline-block;
            object-fit: cover;
            border: 1px solid #e8d8a0;
            background: #fff;
        }
        .profile-avatar-fallback {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff3c4, #ffe08a);
            color: #2d3436;
            font-weight: 700;
            font-size: 22px;
            border: 1px solid #e8d8a0;
        }
        .profile-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        .notification-item {
            border-bottom: 1px solid #eef3f8;
            padding: 10px 14px;
        }
        .notification-item.unread {
            background: #f8fbff;
        }
        .notification-item h6 {
            margin: 0 0 4px;
            font-size: 14px;
        }
        .notification-item p {
            margin: 0 0 4px;
            font-size: 12px;
            color: #5e6b7b;
        }
        .notification-item small {
            color: #8a96a3;
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="main-container">
        <nav id="sidebar" class="sidebar-wrapper">
            <div class="app-brand px-3 pt-2">
                <center>
                    <a href="{{ $homeRoute }}">
                        <img src="{{ asset($assetBase . '/images/logo.png') }}" class="logo" alt="logo" />
                    </a>
                </center>
            </div>

            <div class="sidebarMenuScroll">
                @if($isAdmin)
                    <ul class="sidebar-menu">
                        <li class="{{ request()->routeIs('admin.dashboard') ? 'active current-page' : '' }}"><a href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i><span class="menu-text">{{ __('ui.dashboard') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.sellers') ? 'active current-page' : '' }}"><a href="{{ route('admin.sellers') }}"><i class="bi bi-people"></i><span class="menu-text">{{ __('ui.sellers') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.customers') ? 'active current-page' : '' }}"><a href="{{ route('admin.customers') }}"><i class="bi bi-people-fill"></i><span class="menu-text">{{ __('ui.customers') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.products') ? 'active current-page' : '' }}"><a href="{{ route('admin.products') }}"><i class="bi bi-box-seam"></i><span class="menu-text">{{ __('ui.products') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.orders') ? 'active current-page' : '' }}"><a href="{{ route('admin.orders') }}"><i class="bi bi-card-checklist"></i><span class="menu-text">{{ __('ui.orders') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.leads') ? 'active current-page' : '' }}"><a href="{{ route('admin.leads') }}"><i class="bi bi-person-lines-fill"></i><span class="menu-text">Leads</span></a></li>
                        <li class="{{ request()->routeIs('admin.shipments') ? 'active current-page' : '' }}"><a href="{{ route('admin.shipments') }}"><i class="bi bi-truck"></i><span class="menu-text">Shipments</span></a></li>
                        <li class="{{ request()->routeIs('admin.returns') ? 'active current-page' : '' }}"><a href="{{ route('admin.returns') }}"><i class="bi bi-arrow-counterclockwise"></i><span class="menu-text">{{ __('ui.returns') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.payments') ? 'active current-page' : '' }}"><a href="{{ route('admin.payments') }}"><i class="bi bi-credit-card-2-front-fill"></i><span class="menu-text">{{ __('ui.payments') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.withdrawals') ? 'active current-page' : '' }}"><a href="{{ route('admin.withdrawals') }}"><i class="bi bi-cash-stack"></i><span class="menu-text">{{ __('ui.withdrawals') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.call-center') ? 'active current-page' : '' }}"><a href="{{ route('admin.call-center') }}"><i class="bi bi-telephone"></i><span class="menu-text">{{ __('ui.call_center') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.analytics') ? 'active current-page' : '' }}"><a href="{{ route('admin.analytics') }}"><i class="bi bi-bar-chart-line"></i><span class="menu-text">{{ __('ui.analytics') }}</span></a></li>
                      <!--  <li class="{{ request()->routeIs('admin.marketing') ? 'active current-page' : '' }}"><a href="{{ route('admin.marketing') }}"><i class="bi bi-megaphone"></i><span class="menu-text">Marketing</span></a></li> -->
                        <li class="{{ request()->routeIs('admin.reports') ? 'active current-page' : '' }}"><a href="{{ route('admin.reports') }}"><i class="bi bi-file-earmark-text"></i><span class="menu-text">{{ __('ui.reports') }}</span></a></li>
                        <li class="{{ request()->routeIs('admin.system-settings') ? 'active current-page' : '' }}"><a href="{{ route('admin.system-settings') }}"><i class="bi bi-sliders"></i><span class="menu-text">{{ __('ui.system_settings') }}</span></a></li>
                       <!-- <li class="{{ request()->routeIs('admin.admin-management') ? 'active current-page' : '' }}"><a href="{{ route('admin.admin-management') }}"><i class="bi bi-person-badge"></i><span class="menu-text">Admin Management</span></a></li> -->
                        <li class="{{ request()->routeIs('admin.logs') ? 'active current-page' : '' }}"><a href="{{ route('admin.logs') }}"><i class="bi bi-journal-text"></i><span class="menu-text">Logs</span></a></li>
                    </ul>
                @else
                    <ul class="sidebar-menu">
                        <li class="{{ request()->routeIs('seller.dashboard') ? 'active current-page' : '' }}"><a href="{{ route('seller.dashboard') }}"><i class="bi bi-speedometer2"></i><span class="menu-text">{{ __('ui.dashboard') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.leads') ? 'active current-page' : '' }}"><a href="{{ route('seller.leads') }}"><i class="bi bi-person-lines-fill"></i><span class="menu-text">Leads</span></a></li>
                        <li class="{{ request()->routeIs('seller.orders') ? 'active current-page' : '' }}"><a href="{{ route('seller.orders') }}"><i class="bi bi-card-checklist"></i><span class="menu-text">{{ __('ui.orders') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.returns') ? 'active current-page' : '' }}"><a href="{{ route('seller.returns') }}"><i class="bi bi-arrow-counterclockwise"></i><span class="menu-text">{{ __('ui.returns') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.wallet') ? 'active current-page' : '' }}"><a href="{{ route('seller.wallet') }}"><i class="bi bi-wallet2"></i><span class="menu-text">{{ __('ui.wallet') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.products', 'seller.add-product') ? 'active current-page' : '' }}"><a href="{{ route('seller.products') }}"><i class="bi bi-box"></i><span class="menu-text">{{ __('ui.products') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.call-center') ? 'active current-page' : '' }}"><a href="{{ route('seller.call-center') }}"><i class="bi bi-telephone"></i><span class="menu-text">{{ __('ui.call_center') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.shipments', 'seller.create-shipment') ? 'active current-page' : '' }}"><a href="{{ route('seller.shipments') }}"><i class="bi bi-truck"></i><span class="menu-text">{{ __('ui.shipments') }}</span></a></li>
                        <li class="{{ request()->routeIs('seller.analytics') ? 'active current-page' : '' }}"><a href="{{ route('seller.analytics') }}"><i class="bi bi-bar-chart-line"></i><span class="menu-text">{{ __('ui.analytics') }}</span></a></li>
                     <!--   <li class="{{ request()->routeIs('seller.reports') ? 'active current-page' : '' }}"><a href="{{ route('seller.reports') }}"><i class="bi bi-file-earmark-text"></i><span class="menu-text">Reports</span></a></li> -->
                        <li class="{{ request()->routeIs('seller.csv-import') ? 'active current-page' : '' }}"><a href="{{ route('seller.csv-import') }}"><i class="bi bi-file-earmark-spreadsheet"></i><span class="menu-text">CSV Import</span></a></li>
                        <li class="{{ request()->routeIs('seller.settings') ? 'active current-page' : '' }}"><a href="{{ route('seller.settings') }}"><i class="bi bi-gear-fill"></i><span class="menu-text">{{ __('ui.settings') }}</span></a></li>
                    </ul>
                @endif
            </div>
        </nav>

        <div class="app-container">
            <div class="app-header d-flex align-items-center">
                <div class="d-flex">
                    <button class="btn btn-outline-primary me-2 toggle-sidebar" id="toggle-sidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <button class="btn btn-outline-primary me-2 pin-sidebar" id="pin-sidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                </div>

                <div class="app-brand-sm d-md-none d-sm-block">
                    <a href="{{ $homeRoute }}">
                        <img src="{{ asset($assetBase . '/images/logo-sm.png') }}" class="logo" alt="Spedfly">
                    </a>
                </div>

                <ol class="breadcrumb d-none d-lg-flex ms-3">
                        <li class="breadcrumb-item">
                        <i class="bi bi-house lh-1"></i>
                        <a href="{{ $homeRoute }}" class="text-decoration-none">{{ __('ui.home') }}</a>
                    </li>
                    <li class="breadcrumb-item text-secondary">{{ __('ui.dashboard') }}</li>
                </ol>

                <div class="header-actions">
                    
                     <!--------------------------------------------------------------------------->      
                    
               
                    
                   <div class="me-2">
                        <div class="dropdown">
                            <button class="btn btn-outline border dropdown-toggle d-flex align-items-center gap-2"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                @php
                                    $currentLocale = app()->getLocale();
                                    $languageMap = [
                                        'en' => ['key' => 'english', 'flag' => 'us', 'alt' => 'US flag'],
                                        'fr' => ['key' => 'french', 'flag' => 'fr', 'alt' => 'France flag'],
                                        'es' => ['key' => 'spanish', 'flag' => 'es', 'alt' => 'Spain flag'],
                                        'it' => ['key' => 'italian', 'flag' => 'it', 'alt' => 'Italy flag'],
                                    ];
                                    $currentLanguage = $languageMap[$currentLocale] ?? $languageMap['en'];
                                @endphp
                                <img src="https://flagcdn.com/w20/{{ $currentLanguage['flag'] }}.png" class="flag-icon" alt="{{ $currentLanguage['alt'] }}">
                                {{ __('ui.' . $currentLanguage['key']) }}
                            </button>
                              
                             
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('language.switch', 'en') }}"><img src="https://flagcdn.com/w20/us.png" class="flag-icon" alt="US flag">{{ __('ui.english') }}</a></li>
                               
                                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('language.switch', 'fr') }}"><img src="https://flagcdn.com/w20/fr.png" class="flag-icon" alt="France flag">{{ __('ui.french') }}</a></li>
                                
                                
                              
                               <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('language.switch', 'es') }}"><img src="https://flagcdn.com/w20/es.png" class="flag-icon" alt="Spain flag">{{ __('ui.spanish') }}</a></li>
                            
                               
                               
                                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('language.switch', 'it') }}"><img src="https://flagcdn.com/w20/it.png" class="flag-icon" alt="Italy flag">{{ __('ui.italian') }}</a></li>
                            </ul>
                        </div>
                    </div>   
                  
                    
              <!--------------------------------------------------------------------------->      
                    
                    
                    
                  <!--------------------------------------------------------------------------->      
                    
                     
                    
                    
                    
                    

                    <div class="dropdown border-start">
                        <a class="dropdown-toggle d-flex px-3 py-4 position-relative" href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false" id="notificationDropdown" data-notification-trigger data-feed-url="{{ route('notifications.feed') }}" data-read-url="{{ route('notifications.read-all') }}">
                            <i class="bi bi-bell fs-4 lh-1 text-secondary"></i>
                            <span class="count-label info" id="notificationCountBadge"></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-md notification-dropdown-menu shadow-sm">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                <h6 class="m-0">{{ __('ui.notifications') }}</h6>
                                <button class="btn btn-link btn-sm p-0" type="button" id="markAllReadBtn">{{ __('ui.mark_all_read') }}</button>
                            </div>
                            <div id="notificationList" class="notification-dropdown-body">
                                <div class="notification-item">
                                    <p class="mb-0">{{ __('ui.no_new_notifications') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>




                <!--    <div class="dropdown border-start">
                        <a class="dropdown-toggle d-flex px-3 py-4 position-relative" href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-envelope-open fs-4 lh-1 text-secondary"></i>
                            <span class="count-label"></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-md shadow-sm">
                            <a href="javascript:void(0)" class="dropdown-item">
                                <div class="d-flex py-2">
                                    <img src="{{ asset($assetBase . '/images/user.png') }}" class="msg-avatar me-3" alt="user" />
                                    <div>
                                        <h5 class="mb-1">Stacy Macdonald</h5>
                                        <p class="mb-1 text-secondary">Got a new review. Congratulations!</p>
                                        <p class="small m-0 text-secondary">Today, 07:30pm</p>
                                    </div>
                                </div>
                            </a>
                            <a href="javascript:void(0)" class="dropdown-item">
                                <div class="d-flex py-2">
                                    <img src="{{ asset($assetBase . '/images/user2.png') }}" class="msg-avatar me-3" alt="user" />
                                    <div>
                                        <h5 class="mb-1">Harriet Orozco</h5>
                                        <p class="mb-1 text-secondary">Happy Customer.</p>
                                        <p class="small m-0 text-secondary">Today, 08:00pm</p>
                                    </div>
                                </div>
                            </a>
                            <a href="javascript:void(0)" class="dropdown-item">
                                <div class="d-flex py-2">
                                    <img src="{{ asset($assetBase . '/images/user1.png') }}" class="msg-avatar me-3" alt="user" />
                                    <div>
                                        <h5 class="mb-1">Grady Baxter</h5>
                                        <p class="mb-1 text-secondary">Grady wrote a new comment!</p>
                                        <p class="small m-0 text-secondary">Today, 09:30pm</p>
                                    </div>
                                </div>
                            </a>
                            <div class="d-grid mx-3 my-3">
                                <a href="javascript:void(0)" class="btn btn-warning text-white">View all</a>
                            </div>
                        </div>
                    </div>   -->






                    <div class="dropdown border-start">
                        <a class="dropdown-toggle d-flex px-3 py-3" href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            @if ($profileAvatarUrl)
                                <img src="{{ $profileAvatarUrl }}" class="profile-avatar-sm profile-avatar-image" alt="{{ $profileName }}" />
                            @else
                                <div class="profile-avatar-fallback" style="width:40px;height:40px;font-size:16px;">{{ $profileInitial }}</div>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-mini shadow-sm">
                            <div class="d-flex py-3 px-3 border-bottom">
                                <div class="profile-avatar-fallback me-3" aria-hidden="true">{{ $profileInitial }}</div>
                                <div>
                                    <h5 class="mb-1">{{ $profileName }}</h5>
                                    <p class="m-0 small opacity-50">{{ $profileEmail ?: ($isAdmin ? 'Admin Account' : 'Seller Account') }}</p>
                                </div>
                            </div>
                            <a class="dropdown-item d-flex align-items-center" href="{{ $profileRoute }}">
                                <i class="bi bi-person fs-4 me-2"></i>{{ __('ui.profile') }}
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="{{ $settingsRoute }}">
                                <i class="bi bi-gear fs-4 me-2"></i>{{ __('ui.settings') }}
                            </a>
                            <div class="d-grid p-3 py-2">
                                @if($isAdmin)
                                    <form action="{{ route('admin.logout') }}" method="post">
                                        @csrf
                                        <button type="submit" class="btn btn-warning text-white w-100">{{ __('ui.logout') }}</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.logout') }}" method="post">
                                        @csrf
                                        <button type="submit" class="btn btn-warning text-white w-100">{{ __('ui.logout') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
