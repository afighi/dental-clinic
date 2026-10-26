/**
 * JRC Dental Care - Admin Dashboard
 * Main JavaScript file
 */

$(document).ready(function() {
    // Smooth scrolling for anchor links
    $('a.nav-link[href*="#"]:not([href="#"])').click(function() {
        if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800);
                return false;
            }
        }
    });

    // Fixed navbar on scroll
    $(window).scroll(function() {
        if ($(window).scrollTop() > 50) {
            $('.navbar').addClass('shadow-sm');
        } else {
            $('.navbar').removeClass('shadow-sm');
        }
    });

    // Toggle sidebar on mobile and desktop
    $('#sidebarToggle').click(function() {
        $('.sidebar').toggleClass('collapsed show');
        $('.content-wrapper').toggleClass('expanded');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function(e) {
        if ($(window).width() < 992) {
            if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
                $('.sidebar').addClass('collapsed').removeClass('show');
                $('.content-wrapper').addClass('expanded');
            }
        }
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize date picker for appointments
    if ($('.date-picker').length) {
        $('.date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }

    // Initialize time picker for appointments
    if ($('.time-picker').length) {
        $('.time-picker').timepicker({
            minuteStep: 15,
            showMeridian: true,
            defaultTime: '09:00 AM'
        });
    }

    // Mark notifications as read
    $('.mark-all-read').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/mark_notifications_read.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('.notification-badge').fadeOut().text('0').hide();
                    $('.notification-item').removeClass('unread');
                    showToast('Success', 'All notifications marked as read');
                }
            },
            error: function() {
                $('.notification-item').removeClass('unread');
                $('.notification-badge').text('0').hide();
            }
        });
    });

    // Contact form submission
    $('#contactForm').submit(function(e) {
        e.preventDefault();
        var name = $('#name').val();
        var email = $('#email').val();
        var phone = $('#phone').val();
        var message = $('#message').val();
        
        if (name.trim() === '' || email.trim() === '' || message.trim() === '') {
            alert('Please fill in all required fields');
            return;
        }
        
        alert('Thank you for your message. We will get back to you soon!');
        this.reset();
    });

    // Appointment form submission
    $('#appointmentForm').submit(function(e) {
        e.preventDefault();
        var successMessage = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong> Your appointment has been scheduled. We'll confirm shortly.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $(this).find('.form-message').html(successMessage);
        this.reset();
    });

    // Ajax form submission handler
    $('.ajax-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        const formData = new FormData(this);
        const submitBtn = form.closest('.modal').length ? form.closest('.modal').find('.modal-footer .btn-primary') : form.find('.btn-primary');
        const formMessage = form.find('.form-message');
        
        const originalBtnText = submitBtn.text();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');
        
        $.ajax({
            url: form.attr('action') || 'ajax/form_handler.php',
            type: form.attr('method') || 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false);
                submitBtn.text(originalBtnText);
                
                if (response.success) {
                    formMessage.html(`
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `);
                    
                    if (response.reset) {
                        form[0].reset();
                    }
                    
                    if (response.closeModal) {
                        setTimeout(function() {
                            form.closest('.modal').modal('hide');
                            formMessage.html('');
                        }, 1500);
                    }
                    
                    if (response.reload) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                    
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1500);
                    }
                } else {
                    formMessage.html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ${response.message || 'An error occurred. Please try again.'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false);
                submitBtn.text(originalBtnText);
                
                formMessage.html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        An error occurred: ${xhr.responseJSON?.message || error || 'Unknown error'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
            }
        });
    });

    // Data tables initialization
    if ($('#appointmentsTable').length) {
        $('#appointmentsTable').DataTable({
            responsive: true,
            order: [[2, 'asc']]
        });
    }

    if ($('#patientsTable').length) {
        $('#patientsTable').DataTable({
            responsive: true,
            order: [[1, 'asc']]
        });
    }

    // Analytics Chart Initialization
    if ($('#analyticsChart').length) {
        var ctx = document.getElementById('analyticsChart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Appointments',
                        backgroundColor: 'rgba(24, 6, 168, 0.1)',
                        borderColor: 'rgba(24, 6, 168, 0.8)',
                        data: [15, 12, 18, 14, 20, 10, 8],
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Patients',
                        backgroundColor: 'rgba(225, 162, 200, 0.1)',
                        borderColor: 'rgba(225, 162, 200, 0.8)',
                        data: [10, 8, 12, 9, 14, 7, 5],
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        $('#weeklyChartBtn').click(function() {
            updateChart('weekly');
            $('.btn-group .btn').removeClass('active');
            $(this).addClass('active');
        });

        $('#monthlyChartBtn').click(function() {
            updateChart('monthly');
            $('.btn-group .btn').removeClass('active');
            $(this).addClass('active');
        });

        $('#yearlyChartBtn').click(function() {
            updateChart('yearly');
            $('.btn-group .btn').removeClass('active');
            $(this).addClass('active');
        });

        function updateChart(period) {
            $.ajax({
                url: 'ajax/get_analytics_data.php',
                type: 'GET',
                data: { period: period },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        chart.data.labels = response.labels;
                        chart.data.datasets[0].data = response.appointments;
                        chart.data.datasets[1].data = response.patients;
                        chart.update();
                    } else {
                        setFallbackChartData(period);
                        chart.update();
                    }
                },
                error: function() {
                    setFallbackChartData(period);
                    chart.update();
                }
            });
        }

        function setFallbackChartData(period) {
            if (period === 'weekly') {
                chart.data.labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                chart.data.datasets[0].data = [15, 12, 18, 14, 20, 10, 8];
                chart.data.datasets[1].data = [10, 8, 12, 9, 14, 7, 5];
            } else if (period === 'monthly') {
                chart.data.labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
                chart.data.datasets[0].data = [55, 70, 62, 75];
                chart.data.datasets[1].data = [40, 45, 50, 55];
            } else if (period === 'yearly') {
                chart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                chart.data.datasets[0].data = [200, 180, 220, 240, 250, 260, 270, 280, 260, 240, 220, 230];
                chart.data.datasets[1].data = [150, 140, 160, 170, 180, 190, 200, 210, 190, 180, 170, 175];
            }
        }
    }

    // Patients Chart Initialization
    if ($('#patientsChart').length) {
        var ctx = document.getElementById('patientsChart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Patients',
                    backgroundColor: 'rgba(24, 6, 168, 0.7)',
                    borderColor: 'rgba(24, 6, 168, 1)',
                    data: [12, 19, 8, 15, 12, 6, 3]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Confirm delete actions
    $('.btn-delete').click(function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // File input preview
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
        
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Tab Navigation
    $('#usersTab, #viewAllUsersBtn').click(function(e) {
        e.preventDefault();
        loadUsersTab();
    });

    $('#rolesTab').click(function(e) {
        e.preventDefault();
        loadRolesTab();
    });

    $('#inventoryTab, #viewAllInventoryBtn').click(function(e) {
        e.preventDefault();
        loadInventoryTab();
    });

    $('#suppliersTab').click(function(e) {
        e.preventDefault();
        loadSuppliersTab();
    });

    $('#purchaseOrdersTab, #viewAllPOBtn').click(function(e) {
        e.preventDefault();
        loadPurchaseOrdersTab();
    });

    $('#reportsTab').click(function(e) {
        e.preventDefault();
        loadReportsTab();
    });

    $('#auditTrailTab, #viewAllActivitiesBtn').click(function(e) {
        e.preventDefault();
        loadAuditTrailTab();
    });

    $('#profileTab, #profileLink').click(function(e) {
        e.preventDefault();
        loadProfileTab();
    });

    // Functions to load tab content
    function loadUsersTab() {
        $('.nav-link').removeClass('active');
        $('# EusersTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#usersContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="usersContent"></div>');
            $('#usersContent').addClass('show active').html(`
                <h2 class="mb-4">User Management</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Users management content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#usersContent').addClass('show active');
        }
    }

    function loadRolesTab() {
        $('.nav-link').removeClass('active');
        $('#rolesTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#rolesContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="rolesContent"></div>');
            $('#rolesContent').addClass('show active').html(`
                <h2 class="mb-4">Roles Management</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Roles management content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#rolesContent').addClass('show active');
        }
    }

    function loadInventoryTab() {
        $('.nav-link').removeClass('active');
        $('#inventoryTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#inventoryContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="inventoryContent"></div>');
            $('#inventoryContent').addClass('show active').html(`
                <h2 class="mb-4">Inventory Management</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Inventory management content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#inventoryContent').addClass('show active');
        }
    }

    function loadSuppliersTab() {
        $('.nav-link').removeClass('active');
        $('#suppliersTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#suppliersContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="suppliersContent"></div>');
            $('#suppliersContent').addClass('show active').html(`
                <h2 class="mb-4">Suppliers Management</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Suppliers management content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#suppliersContent').addClass('show active');
        }
    }

    function loadPurchaseOrdersTab() {
        $('.nav-link').removeClass('active');
        $('#purchaseOrdersTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#purchaseOrdersContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="purchaseOrdersContent"></div>');
            $('#purchaseOrdersContent').addClass('show active').html(`
                <h2 class="mb-4">Purchase Orders</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Purchase orders content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#purchaseOrdersContent').addClass('show active');
        }
    }

    function loadReportsTab() {
        $('.nav-link').removeClass('active');
        $('#reportsTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#reportsContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="reportsContent"></div>');
            $('#reportsContent').addClass('show active').html(`
                <h2 class="mb-4">Reports</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>Reports content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#reportsContent').addClass('show active');
        }
    }

    function loadAuditTrailTab() {
        $('.nav-link').removeClass('active');
        $('#auditTrailTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#auditTrailContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="auditTrailContent"></div>');
            $('#auditTrailContent').addClass('show active').html(`
                <h2 class="mb-4">Audit Trail</h2>
                <div class="card dashboard-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">System Activity Log</h5>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" class="form-control" placeholder="Search activities...">
                            <button class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="auditTrailTable">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading audit trail data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `);

            $.ajax({
                url: 'ajax/get_audit_trail.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#auditTrailTable tbody').empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, activity) {
                            let action = activity.action_performed;
                            if (action.toLowerCase() === 'update' && 
                                (activity.details && (activity.details.toLowerCase().includes('password') || 
                                                     activity.details.toLowerCase().includes('reset')))) {
                                action = 'Forgot Password';
                            }
                            
                            let details = activity.details;
                            if (action.toLowerCase().includes('password') || 
                                action === 'Forgot Password' ||
                                (details && details.toLowerCase().includes('password'))) {
                                details = "User performed forgot password: " + details;
                            }
                            
                            let row = `<tr>
                                <td>${activity.first_name} ${activity.last_name} (${activity.username})</td>
                                <td>${action}</td>
                                <td>${activity.table_affected}</td>
                                <td>${details}</td>
                                <td>${formatDateTime(activity.timestamp)}</td>
                                <td>${activity.ip_address}</td>
                            </tr>`;
                            $('#auditTrailTable tbody').append(row);
                        });
                    } else {
                        $('#auditTrailTable tbody').html('<tr><td colspan="6" class="text-center">No audit trail records found.</td></tr>');
                    }

                    if ($.fn.DataTable.isDataTable('#auditTrailTable')) {
                        $('#auditTrailTable').DataTable().destroy();
                    }
                    $('#auditTrailTable').DataTable({
                        responsive: true,
                        order: [[4, 'desc']],
                        pageLength: 25,
                        language: {
                            search: "Search audit trail:"
                        }
                    });
                },
                error: function(xhr, status, error) {
                    $('#auditTrailTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading data. Please try again.</td></tr>');
                    console.error('AJAX Error:', error);
                }
            });
        } else {
            $('#auditTrailContent').addClass('show active');
        }
    }

    function loadProfileTab() {
        $('.nav-link').removeClass('active');
        $('#profileTab').addClass('active');
        $('.tab-content .tab-pane').removeClass('show active');
        
        if ($('#profileContent').length === 0) {
            $('.tab-content').append('<div class="tab-pane fade" id="profileContent"></div>');
            $('#profileContent').addClass('show active').html(`
                <h2 class="mb-4">My Profile</h2>
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <p>User profile content will be loaded here.</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            $('#profileContent').addClass('show active');
        }
    }

    // Helper function to format dates
    function formatDateTime(dateTimeStr) {
        const dt = new Date(dateTimeStr);
        return dt.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Toast notification function
    function showToast(title, message) {
        // Assuming a toast container exists in the HTML
        const toast = $(`
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `);
        $('#toastContainer').append(toast);
        toast.toast({ delay: 3000 });
        toast.toast('show');
    }
});

