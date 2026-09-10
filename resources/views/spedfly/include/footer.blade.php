@php
    $isAdmin = request()->is('admin*');
    $assetBase = $isAdmin ? 'assets/admin' : 'assets/seller';
@endphp
            <div class="app-footer">
                <span>&copy; Spedfly {{ date('Y') }}</span>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset($assetBase . '/js/jquery.min.js') }}"></script>
<script src="{{ asset($assetBase . '/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset($assetBase . '/vendor/overlay-scroll/jquery.overlayScrollbars.min.js') }}"></script>
<script src="{{ asset($assetBase . '/vendor/overlay-scroll/custom-scrollbar.js') }}"></script>
<script src="{{ asset($assetBase . '/vendor/toastify/custom.js') }}"></script>
<script src="{{ asset($assetBase . '/vendor/apex/apexcharts.min.js') }}"></script>
<script src="{{ asset($assetBase . '/js/custom.js') }}"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@stack('page-scripts')
<script>
  $(function () {
    var $trigger = $('[data-notification-trigger]').first();
    if (! $trigger.length) {
      return;
    }

    var feedUrl = $trigger.data('feed-url');
    var readUrl = $trigger.data('read-url');
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var $count = $('#notificationCountBadge');
    var $list = $('#notificationList');
    var $markAllBtn = $('#markAllReadBtn');
    var lastNotificationIdKey = 'spedfly:last-notification-id:' + window.location.pathname;
    var lastNotificationId = parseInt(window.localStorage.getItem(lastNotificationIdKey) || '0', 10) || 0;
    var shouldAutoRefresh = window.location.pathname.indexOf('/seller/') !== -1;
    var sellerRefreshInFlight = false;

    function escapeHtml(text) {
      return $('<div>').text(text || '').html();
    }

    function destroyDataTableIfNeeded(selector) {
      if (! $.fn.DataTable) {
        return;
      }

      var $table = $(selector);
      if (! $table.length) {
        return;
      }

      if ($.fn.DataTable.isDataTable($table[0])) {
        $table.DataTable().destroy();
      }
    }

    function initRecentShipmentsTable() {
      if (! $('#recentShipmentsTable').length || ! $.fn.DataTable) {
        return;
      }

      if ($('#recentShipmentsTable tbody tr.empty-row').length > 0) {
        return;
      }

      $('#recentShipmentsTable').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        ordering: false,
        searching: false,
        info: false
      });
    }

    function initOrdersTable() {
      if (! $('#ordersTable').length || ! $.fn.DataTable) {
        return;
      }

      if ($('#ordersTable tbody tr.empty-row').length > 0) {
        return;
      }

      $('#ordersTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        iDisplayLength: 5
      });
    }

    function initCallCenterTable() {
      if (! $('#callCenterTable').length || ! $.fn.DataTable) {
        return;
      }

      $('#callCenterTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        iDisplayLength: 5,
        order: []
      });
    }

    function initShipmentsTable() {
      if (! $('#shipments-table').length || ! $.fn.DataTable) {
        return;
      }

      $('#shipments-table').DataTable({
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
        ordering: false,
        dom: 'lfrtip',
        language: {
          search: 'Quick search:',
          searchPlaceholder: 'Search visible rows'
        },
        autoWidth: false
      });
    }

    function refreshSellerTablesFromHtml(html) {
      var sourceDoc = new DOMParser().parseFromString(html, 'text/html');
      var refreshed = false;
      var pathname = window.location.pathname || '';

      function replaceSection(currentSelector, sourceSelector) {
        var currentEl = document.querySelector(currentSelector);
        var sourceEl = sourceDoc.querySelector(sourceSelector || currentSelector);

        if (! currentEl || ! sourceEl) {
          return false;
        }

        currentEl.replaceWith(sourceEl.cloneNode(true));
        return true;
      }

      if (pathname.indexOf('/seller/dashboard') !== -1) {
        destroyDataTableIfNeeded('#recentShipmentsTable');
        refreshed = replaceSection('#sellerDashboardHero') || refreshed;
        refreshed = replaceSection('#sellerDashboardMetrics') || refreshed;
        refreshed = replaceSection('#sellerDashboardOverviewSection') || refreshed;
        refreshed = replaceSection('#sellerDashboardFees') || refreshed;
        refreshed = replaceSection('#sellerRecentShipmentsSection') || refreshed;
        if (refreshed && window.SpedflySellerDashboard && typeof window.SpedflySellerDashboard.init === 'function') {
          var payloadNode = sourceDoc.querySelector('#sellerDashboardChartData');
          var payload = null;

          if (payloadNode) {
            try {
              payload = JSON.parse(payloadNode.textContent || '{}');
            } catch (error) {
              payload = null;
            }
          }

          if (payload) {
            window.SpedflySellerDashboard.init(payload);
          }
        }
        return refreshed;
      }

      if (pathname.indexOf('/seller/orders') !== -1) {
        destroyDataTableIfNeeded('#ordersTable');
        refreshed = replaceSection('#ordersTable') || refreshed;
        if (refreshed) {
          initOrdersTable();
        }
        return refreshed;
      }

      if (pathname.indexOf('/seller/returns') !== -1) {
        destroyDataTableIfNeeded('#returnsTable');
        refreshed = replaceSection('#returnsTable') || refreshed;
        if (refreshed && $.fn.DataTable && $('#returnsTable').length && ! $('#returnsTable tbody tr.empty-row').length) {
          $('#returnsTable').DataTable({
            aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
            iDisplayLength: 5,
            order: []
          });
        }
        return refreshed;
      }

      if (pathname.indexOf('/seller/call-center') !== -1) {
        destroyDataTableIfNeeded('#callCenterTable');
        refreshed = replaceSection('#callCenterLiveSection') || refreshed;
        if (refreshed) {
          initCallCenterTable();
        }
        return refreshed;
      }

      if (pathname.indexOf('/seller/shipments') !== -1) {
        destroyDataTableIfNeeded('#shipments-table');
        refreshed = replaceSection('#sellerShipmentsTableSection') || refreshed;
        if (refreshed) {
          initShipmentsTable();
        }
        return refreshed;
      }

      return false;
    }

    function notificationIcon(type) {
      switch ((type || '').toLowerCase()) {
        case 'shipment_created':
          return 'bi-truck';
        case 'order_status':
          return 'bi-box-seam';
        case 'call_center':
          return 'bi-telephone';
        case 'return_status':
          return 'bi-arrow-counterclockwise';
        case 'payment_failed':
          return 'bi-exclamation-triangle';
        case 'csv_import':
          return 'bi-file-earmark-arrow-up';
        case 'wallet_low_balance':
          return 'bi-wallet2';
        default:
          return 'bi-bell';
      }
    }

    function updateCount(unreadCount) {
      if (unreadCount > 0) {
        $count.text(unreadCount);
      } else {
        $count.text('');
      }
    }

    function renderItems(items) {
      if (! items.length) {
        $list.html('<div class="notification-item"><p class="mb-0">No new notifications</p></div>');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += '<div class="notification-item ' + (item.is_read ? '' : 'unread') + '">'
          + '<div class="d-flex align-items-start gap-2">'
          + '<div class="flex-shrink-0"><i class="bi ' + notificationIcon(item.type) + '"></i></div>'
          + '<div class="flex-grow-1">'
          + '<h6>' + escapeHtml(item.title) + '</h6>'
          + '<p>' + escapeHtml(item.message) + '</p>'
          + '<small>' + escapeHtml(item.created_at || '') + '</small>'
          + '</div>'
          + '</div>'
          + '</div>';
      });
      $list.html(html);
    }

    function fetchNotifications() {
      $.get(feedUrl)
        .done(function (res) {
          var items = Array.isArray(res.items) ? res.items : [];
          var latestItem = items.length ? items[0] : null;
          var shouldRefreshSellerPage = false;

          if (latestItem && latestItem.id) {
            var latestId = parseInt(latestItem.id, 10) || 0;

            if (!lastNotificationId) {
              lastNotificationId = latestId;
              window.localStorage.setItem(lastNotificationIdKey, String(lastNotificationId));
            } else if (shouldAutoRefresh && latestId > lastNotificationId) {
              lastNotificationId = latestId;
              window.localStorage.setItem(lastNotificationIdKey, String(lastNotificationId));
              shouldRefreshSellerPage = true;
            }
          }

          updateCount(res.unread_count || 0);
          renderItems(items);

          if (shouldRefreshSellerPage && !sellerRefreshInFlight) {
            sellerRefreshInFlight = true;
            $.get(window.location.href)
              .done(function (html) {
                refreshSellerTablesFromHtml(html);
              })
              .always(function () {
                sellerRefreshInFlight = false;
              });
          }
        });
    }

    function markAllRead() {
      return $.post(readUrl, {
        _token: csrf
      }).done(function () {
        updateCount(0);
        fetchNotifications();
      });
    }

    fetchNotifications();
    setInterval(fetchNotifications, 5000);

    $markAllBtn.on('click', function () {
      markAllRead();
    });

    var triggerEl = document.getElementById('notificationDropdown');
    if (triggerEl) {
      triggerEl.addEventListener('shown.bs.dropdown', function () {
        markAllRead();
      });
    }
  });
</script>

</body>
</html>
