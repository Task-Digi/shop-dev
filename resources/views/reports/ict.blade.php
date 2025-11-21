<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ICT LIST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
        }
        .quantity-input {
            width: 50px;
            text-align: center;
            font-size: 12px;
            padding: 1px 4px;
            height: 24px;
        }
        .btn-quantity {
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-weight: bold;
            font-size: 10px;
        }
        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            font-size: 13px;
        }
        .table td {
            font-size: 13px;
            vertical-align: middle;
        }
        .status-message {
            font-size: 10px;
            display: block;
            margin-top: 1px;
            height: 12px;
        }
        .saving {
            color: #0d6efd;
        }
        .success {
            color: #198754;
        }
        .error {
            color: #dc3545;
        }
        .loading {
            opacity: 0.6;
        }
    </style>
</head>
<body>
    @include('layouts.nav_bar')

    <div class="container-fluid mt-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">ICT Products List</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('ict.search') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" name="search" class="form-control form-control-sm"
                                placeholder="Search by EAN Code, Colour Name, Base Description, etc." 
                                value="{{ request('search') }}">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm me-2">Search</button>
                            <a href="{{ route('ict') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
                </form>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>EAN CODE</th>
                                <th>COLOUR CODE</th>
                                <th>COLOUR NAME</th>
                                <th>FINISH</th>
                                <th>TIN SIZE</th>
                                <th>EAN CODE BASE</th>
                                <th class="text-center">QTY</th>
                                <th>BASE DESCRIPTION</th>
                                <th>BASE CODE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ictData as $ict)
                                <tr id="row-{{ $ict->id }}">
                                    <td>{{ $ict->id }}</td>
                                    <td>{{ $ict->ean_code }}</td>
                                    <td>{{ $ict->colour_code }}</td>
                                    <td>{{ $ict->colour_name }}</td>
                                    <td>{{ $ict->finish }}</td>
                                    <td>{{ $ict->tin_size }}</td>
                                    <td>{{ $ict->ean_code_base }}</td>
                                    <td class="text-center">
                                        <div class="quantity-control">
                                            <button class="btn btn-sm btn-outline-danger btn-quantity" 
                                                    onclick="updateQuantity({{ $ict->id }}, -1)"
                                                    title="Decrease Quantity">
                                                −
                                            </button>
                                            <input type="number" 
                                                   class="form-control form-control-sm quantity-input" 
                                                   id="qty-input-{{ $ict->id }}" 
                                                   data-ean="{{ $ict->ean_code_base }}"
                                                   value="{{ $ict->qty ?? 0 }}" 
                                                   min="0"
                                                   onchange="updateQuantityDirect({{ $ict->id }})"
                                                   onblur="validateQuantity({{ $ict->id }})">
                                            <button class="btn btn-sm btn-outline-success btn-quantity" 
                                                    onclick="updateQuantity({{ $ict->id }}, 1)"
                                                    title="Increase Quantity">
                                                +
                                            </button>
                                        </div>
                                        <small class="status-message" id="status-{{ $ict->id }}"></small>
                                    </td>
                                    <td>{{ $ict->base_description }}</td>
                                    <td>{{ $ict->base_code }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No products found. Try adjusting your search criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($ictData instanceof \Illuminate\Pagination\LengthAwarePaginator && $ictData->hasPages())
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Showing {{ $ictData->firstItem() }} to {{ $ictData->lastItem() }} of {{ $ictData->total() }} results
                        </div>
                        <nav>
                            {{ $ictData->links() }}
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    
    <script>
        // Store original values for error recovery
        const originalValues = new Map();

        // Function to show status message
        function showStatus(id, message, type = 'info') {
            const statusElement = document.getElementById(`status-${id}`);
            statusElement.textContent = message;
            statusElement.className = `status-message ${type}`;
            
            // Clear status after 3 seconds (except for errors)
            if (type !== 'error') {
                setTimeout(() => {
                    if (statusElement.textContent === message) {
                        statusElement.textContent = '';
                    }
                }, 3000);
            }
        }

        // Function to validate quantity input
        function validateQuantity(id) {
            const input = document.getElementById(`qty-input-${id}`);
            let value = parseInt(input.value);
            
            if (isNaN(value) || value < 0) {
                input.value = 0;
                updateDatabase(id, 0);
            }
        }

        // Function to update quantity using +/- buttons
        function updateQuantity(id, change) {
            const input = document.getElementById(`qty-input-${id}`);
            let currentValue = parseInt(input.value) || 0;
            let newValue = currentValue + change;
            
            // Ensure value doesn't go below 0
            if (newValue < 0) {
                newValue = 0;
                showStatus(id, 'Minimum quantity is 0', 'error');
            }
            
            input.value = newValue;
            
            // Update database
            updateDatabase(id, newValue);
        }
        
        // Function to update quantity when manually changed in input field
        function updateQuantityDirect(id) {
            const input = document.getElementById(`qty-input-${id}`);
            let newValue = parseInt(input.value);
            
            // Validate input
            if (isNaN(newValue) || newValue < 0) {
                newValue = 0;
                input.value = 0;
                showStatus(id, 'Quantity set to 0', 'error');
            }
            
            // Update database
            updateDatabase(id, newValue);
        }
        
        // Function to send AJAX request to update database
        function updateDatabase(id, quantity) {
            const input = document.getElementById(`qty-input-${id}`);
            const ean = input.getAttribute('data-ean');

            // Store original value for error recovery
            if (!originalValues.has(id)) {
                originalValues.set(id, parseInt(input.value));
            }
            
            // Show loading status and disable inputs
            showStatus(id, 'Saving...', 'saving');
            setInputState(id, true);
            
            // Create form data
            const formData = new FormData();
            formData.append('id', id); // Keep ID for logging/reference if needed
            formData.append('ean_code_base', ean);
            formData.append('quantity', quantity);
            formData.append('_token', '{{ csrf_token() }}');
            
            // Send AJAX request
            fetch('{{ route("ict.update-quantity") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showStatus(id, 'Saved', 'success');
                    // Clear original value on success
                    originalValues.delete(id);
                    
                    // Optional: Update other inputs with same EAN if present on page
                    updateOtherInputs(ean, quantity, id);
                } else {
                    throw new Error(data.message || 'Server returned error');
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                showStatus(id, 'Save failed', 'error');
                
                // Revert to original value on error
                const originalValue = originalValues.get(id);
                if (originalValue !== undefined) {
                    input.value = originalValue;
                }
            })
            .finally(() => {
                // Re-enable inputs
                setInputState(id, false);
                
                // Clear original values after a delay
                setTimeout(() => {
                    originalValues.delete(id);
                }, 5000);
            });
        }

        // Update other inputs with the same EAN
        function updateOtherInputs(ean, quantity, skipId) {
            const inputs = document.querySelectorAll(`input[data-ean="${ean}"]`);
            inputs.forEach(input => {
                const id = input.id.replace('qty-input-', '');
                if (id != skipId) {
                    input.value = quantity;
                    // Visual feedback
                    input.classList.add('bg-success', 'text-white');
                    setTimeout(() => {
                        input.classList.remove('bg-success', 'text-white');
                    }, 1000);
                }
            });
        }

        // Function to set input state (loading/ready)
        function setInputState(id, isLoading) {
            const input = document.getElementById(`qty-input-${id}`);
            const minusBtn = input.previousElementSibling;
            const plusBtn = input.nextElementSibling;
            
            if (isLoading) {
                input.disabled = true;
                minusBtn.disabled = true;
                plusBtn.disabled = true;
                input.parentElement.parentElement.classList.add('loading');
            } else {
                input.disabled = false;
                minusBtn.disabled = false;
                plusBtn.disabled = false;
                input.parentElement.parentElement.classList.remove('loading');
            }
        }

        // Auto-save when user stops typing (debounced)
        let typingTimers = new Map();
        const doneTypingInterval = 1000; // 1 second

        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('quantity-input')) {
                const id = event.target.id.replace('qty-input-', '');
                
                // Clear existing timer
                if (typingTimers.has(id)) {
                    clearTimeout(typingTimers.get(id));
                }
                
                // Show typing indicator
                showStatus(id, 'Typing...', 'saving');
                
                // Set new timer
                typingTimers.set(id, setTimeout(() => {
                    updateQuantityDirect(parseInt(id));
                    typingTimers.delete(id);
                }, doneTypingInterval));
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Focus on search input when Ctrl+F is pressed
            if (event.ctrlKey && event.key === 'f') {
                event.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            
            // Enter key to save immediately
            if (event.target.classList.contains('quantity-input') && event.key === 'Enter') {
                event.preventDefault();
                const id = event.target.id.replace('qty-input-', '');
                updateQuantityDirect(parseInt(id));
            }
        });

        // Handle page beforeunload to warn about unsaved changes
        window.addEventListener('beforeunload', function(event) {
            if (originalValues.size > 0) {
                event.preventDefault();
                event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>