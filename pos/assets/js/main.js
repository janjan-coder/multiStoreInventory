// Global variables
let cartItems = [];
const emptyCart = document.getElementById('emptyCart');
const subtotalElement = document.getElementById('subtotal');
const taxElement = document.getElementById('tax');
const totalElement = document.getElementById('total');
const checkoutButton = document.getElementById('checkoutButton');
const clearCartButton = document.getElementById('clearCartButton');
const paymentMethod = document.getElementById('paymentMethod');
const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

// DOM Elements
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const categoryFilter = document.getElementById('categoryFilter');
const searchResults = document.getElementById('searchResults');
const cartItemsContainer = document.getElementById('cartItems');
const amountReceived = document.getElementById('amountReceived');
const change = document.getElementById('change');
const processPaymentButton = document.getElementById('processPayment');

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Search functionality
    searchButton.addEventListener('click', () => searchProducts());
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') searchProducts();
    });
    categoryFilter.addEventListener('change', () => searchProducts());

    // Cart management
    checkoutButton.addEventListener('click', showPaymentModal);
    clearCartButton.addEventListener('click', clearCart);

    // Payment processing
    amountReceived.addEventListener('input', calculateChange);
    processPaymentButton.addEventListener('click', processPayment);
});

// Search products
function searchProducts() {
    const searchTerm = searchInput.value.trim();
    const category = categoryFilter.value;

    if (searchTerm.length < 2) {
        showAlert('Please enter at least 2 characters to search', 'warning');
        return;
    }

    fetch('includes/search_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `search=${encodeURIComponent(searchTerm)}&category=${encodeURIComponent(category)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySearchResults(data.products);
        } else {
            showAlert(data.message || 'Error searching products', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error searching products', 'danger');
    });
}

// Display search results
function displaySearchResults(products) {
    searchResults.innerHTML = '';
    
    if (products.length === 0) {
        searchResults.innerHTML = `
            <div class="col-12 text-center py-4">
                <i class="bi bi-search display-4 text-muted"></i>
                <p class="mt-2 text-muted">No products found</p>
            </div>
        `;
        return;
    }

    products.forEach(product => {
        const productCard = document.createElement('div');
        productCard.className = 'col-md-4 col-lg-3';
        productCard.innerHTML = `
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text">
                        <strong>Price:</strong> $${formatPrice(product.price)}<br>
                        <strong>Stock:</strong> ${product.quantity}
                    </p>
                    <button class="btn btn-primary w-100" 
                            onclick="addToCart(${product.id}, '${product.name}', ${product.price}, ${product.quantity})"
                            ${product.quantity <= 0 ? 'disabled' : ''}>
                        ${product.quantity <= 0 ? 'Out of Stock' : 'Add to Cart'}
                    </button>
                </div>
            </div>
        `;
        searchResults.appendChild(productCard);
    });
}

// Add item to cart
function addToCart(id, name, price, stock) {
    const existingItem = cartItems.find(item => item.id === id);
    
    if (existingItem) {
        if (existingItem.quantity >= stock) {
            showAlert('Not enough stock available', 'warning');
            return;
        }
        existingItem.quantity++;
    } else {
        cartItems.push({ id, name, price, quantity: 1 });
    }
    
    updateCartDisplay();
}

// Update cart display
function updateCartDisplay() {
    cartItemsContainer.innerHTML = '';
    
    if (cartItems.length === 0) {
        emptyCart.style.display = 'block';
        cartItemsContainer.style.display = 'none';
        checkoutButton.disabled = true;
        clearCartButton.disabled = true;
        return;
    }

    emptyCart.style.display = 'none';
    cartItemsContainer.style.display = 'block';
    checkoutButton.disabled = false;
    clearCartButton.disabled = false;

    let subtotal = 0;
    
    cartItems.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item mb-3';
        itemElement.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">${item.name}</h6>
                    <small class="text-muted">$${formatPrice(item.price)} each</small>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, -1)">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, 1)">+</button>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        cartItemsContainer.appendChild(itemElement);
    });

    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;

    subtotalElement.textContent = `$${formatPrice(subtotal)}`;
    taxElement.textContent = `$${formatPrice(tax)}`;
    totalElement.textContent = `$${formatPrice(total)}`;
}

// Update item quantity
function updateQuantity(id, change) {
    const item = cartItems.find(item => item.id === id);
    if (!item) return;

    const newQuantity = item.quantity + change;
    if (newQuantity <= 0) {
        removeFromCart(id);
        return;
    }

    // Check stock availability
    fetch(`includes/check_stock.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && newQuantity <= data.stock) {
                item.quantity = newQuantity;
                updateCartDisplay();
            } else {
                showAlert('Not enough stock available', 'warning');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error checking stock', 'danger');
        });
}

// Remove item from cart
function removeFromCart(id) {
    cartItems = cartItems.filter(item => item.id !== id);
    updateCartDisplay();
}

// Clear cart
function clearCart() {
    if (confirm('Are you sure you want to clear the cart?')) {
        cartItems = [];
        updateCartDisplay();
    }
}

// Show payment modal
function showPaymentModal() {
    const total = parseFloat(totalElement.textContent.replace('$', ''));
    amountReceived.value = '';
    change.value = '';
    paymentModal.show();
}

// Calculate change
function calculateChange() {
    const total = parseFloat(totalElement.textContent.replace('$', ''));
    const received = parseFloat(amountReceived.value) || 0;
    const changeAmount = received - total;
    
    change.value = changeAmount >= 0 ? `$${formatPrice(changeAmount)}` : 'Insufficient amount';
    processPaymentButton.disabled = changeAmount < 0;
}

// Process payment
function processPayment() {
    const total = parseFloat(totalElement.textContent.replace('$', ''));
    const received = parseFloat(amountReceived.value);
    const method = paymentMethod.value;

    if (received < total) {
        showAlert('Insufficient payment amount', 'warning');
        return;
    }

    const paymentData = {
        items: cartItems,
        total: total,
        payment_method: method,
        amount_received: received
    };

    fetch('includes/process_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            paymentModal.hide();
            showAlert('Payment processed successfully', 'success');
            cartItems = [];
            updateCartDisplay();
            // Refresh recent sales
            location.reload();
        } else {
            showAlert(data.message || 'Error processing payment', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error processing payment', 'danger');
    });
}

// Helper function to format price
function formatPrice(price) {
    return price.toFixed(2);
}

// Show alert message
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '1050';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
} 