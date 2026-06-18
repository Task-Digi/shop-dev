@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery for AJAX and DOM manipulation -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- Bootstrap CSS for styling -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

        <!-- Custom styles for KS buttons -->
        <style>
            .ks-button {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
                transition: background-color 0.3s ease;
            }

            /* Default gray color for KS button when customer is not bankrupt */
            .ks-button.inactive {
                background-color: #6c757d;
                color: white;
            }

            /* Green color for KS button when customer is bankrupt */
            .ks-button.active {
                background-color: #28a745;
                color: white;
            }

            .ks-button:hover {
                opacity: 0.8;
            }

            /* Table styling */
            .customer-table {
                width: 100%;
                margin-top: 20px;
                border-collapse: collapse;
            }

            .customer-table th,
            .customer-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .customer-table th {
                background-color: #f8f9fa;
                font-weight: bold;
            }

            .customer-table tr:hover {
                background-color: #f5f5f5;
            }

            .search-container {
                margin-bottom: 20px;
            }

            .search-container input {
                width: 100%;
                max-width: 400px;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            .search-container button {
                margin-left: 10px;
                padding: 10px 20px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }

            .search-container button:hover {
                background-color: #0056b3;
            }

            .page-header {
                margin-bottom: 30px;
            }
        </style>
    </head>

    @include('layouts.nav_bar')

    <div class="container mt-4">
        <div class="page-header">
            <h2>Bankruptcy (KS) Customer Management</h2>
            <!-- <p class="text-muted">Click the KS button to mark a customer as bankrupt (green) or remove the status (gray)</p> -->
        </div>

        <!-- Search Section -->
        <div class="search-container">
            <form method="GET" action="{{ route('report.ks') }}" id="searchForm" style="display: flex;">
                <input 
                    type="text" 
                    name="search" 
                    id="searchInput" 
                    value="{{ $search }}"
                    placeholder="Search customer name..." 
                >
                <button type="submit">Search</button>
            </form>
        </div>

        <!-- Customers Table -->
        <div class="table-responsive">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Customer ID</th>
                        <th>Bankrupt Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->customer_name }}</td>
                            <td>{{ $customer->customer_id }}</td>
                            <td>
                                <!-- KS Button: Changes color when clicked and updates database -->
                                <button 
                                    class="ks-button {{ ($customer->KS_exists ?? 0) == 1 ? 'active' : 'inactive' }}"
                                    data-customer-id="{{ $customer->customer_id }}"
                                    data-customer-name="{{ $customer->customer_name }}"
                                    data-ks-status="{{ $customer->KS_exists ?? 0 }}"
                                    onclick="toggleKsStatus(this)"
                                >
                                    {{ ($customer->KS_exists ?? 0) == 1 ? 'KS Active' : 'Mark KS' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px; color: #999;">
                                No customers found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript for KS Button Functionality -->
    <script>
        /**
         * Toggle KS (Bankruptcy) Status
         * This function:
         * 1. Changes button color from gray to green (or vice versa)
         * 2. Sends an AJAX request to update the database
         * 3. Updates the button text to reflect the new status
         */
        function toggleKsStatus(button) {
            // Get the current status from the button's data attribute
            const customerId = button.getAttribute('data-customer-id');
            const customerName = button.getAttribute('data-customer-name');
            const currentStatus = parseInt(button.getAttribute('data-ks-status'));

            // Toggle the status: if 0 (inactive) make it 1 (active), if 1 make it 0
            const newStatus = currentStatus === 1 ? 0 : 1;

            // Send AJAX request to update database
            $.ajax({
                url: '{{ route("update.ks.status") }}', // The update-ks-status route
                type: 'POST',
                data: {
                    customer_id: customerId,
                    KS_exists: newStatus,
                    _token: '{{ csrf_token() }}' // CSRF token for security
                },
                success: function(response) {
                    if (response.success) {
                        // Update the button's status attribute to reflect new state
                        button.setAttribute('data-ks-status', newStatus);

                        // Update button appearance based on new status
                        if (newStatus === 1) {
                            // Status is now active (1): green button
                            button.classList.remove('inactive');
                            button.classList.add('active');
                            button.textContent = 'KS Active';
                        } else {
                            // Status is now inactive (0): gray button
                            button.classList.remove('active');
                            button.classList.add('inactive');
                            button.textContent = 'Mark KS';
                        }
                        // Status updated silently (no popup alert)
                    }
                    // No error popup (silent fail)
                },
                error: function(xhr, status, error) {
                    // Handle AJAX error silently
                    console.error('AJAX Error:', error);
                    // No alert popup
                }
            });
        }
    </script>

@endsection
