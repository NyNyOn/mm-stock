/**
 * File: public/js/po-quick-add.js
 * Description: Visual Catalog Quick Add for Purchase Orders
 */


// =============================================================================
// GLOBAL VARIABLES (Accessible to all functions)
// =============================================================================
let currentPoId = null;
let currentCategoryFilter = 'all';
let selectedItem = null;
let searchTimeout = null;
let hasAddedItems = false; // Track if items caused updates

// Initialize on load
document.addEventListener('DOMContentLoaded', function () {
    // Load Categories (if not already loaded)
    if ($('#catalog-categories button').length <= 1) {
        loadCategoryFilters();
    }

    // Sync inputs on manual typing
    $('#sel-quantity').on('input', function () {
        $('#mob-sel-quantity').val($(this).val());
    });
    $('#mob-sel-quantity').on('input', function () {
        $('#sel-quantity').val($(this).val());
    });
});

// =============================================================================
// GLOBAL FUNCTIONS
// =============================================================================

function openQuickAddModal(poId) {
    currentPoId = poId;
    hasAddedItems = false; // Reset flag
    // Reset type if poId is numeric (meaning we are editing a specific existing PO)
    if (!isNaN(poId) && poId !== 'scheduled' && poId !== 'urgent') {
        window.currentQuickAddType = null;
    }
    currentCategoryFilter = 'all';
    selectedItem = null;

    // Clear UI
    $('#catalog-search').val('');
    $('#catalog-grid').empty();
    hideSelectionPanel();

    // Load Initial Data (if not loaded)
    loadCatalogItems();

    // Show Modal
    if (typeof showModal === 'function') {
        showModal('select-item-modal');
    } else {
        $('#select-item-modal').removeClass('hidden').addClass('flex');
    }
}

// Custom Close Function - Reloads if changes made
window.closeQuickAddModal = function () {
    if (hasAddedItems) {
        window.location.reload();
    } else {
        if (typeof closeModal === 'function') {
            closeModal('select-item-modal');
        } else {
            $('#select-item-modal').addClass('hidden').removeClass('flex');
        }
    }
}

// Wrapper Functions for Auto-Creation
window.openQuickAddModalScheduled = function () {
    window.currentQuickAddType = 'scheduled';
    openQuickAddModal('scheduled');
};

window.openQuickAddModalUrgent = function () {
    window.currentQuickAddType = 'urgent';
    openQuickAddModal('urgent');
};

// Expose openQuickAddModal to window scope
// (Already exported inside DOMContentLoaded)

// =============================================================================
// 2. Data Loading & Rendering
// =============================================================================

function loadCatalogItems() {
    const search = $('#catalog-search').val();

    // Show Loader
    $('#catalog-loader').removeClass('hidden');
    $('#catalog-grid').addClass('opacity-50');
    $('#catalog-empty').addClass('hidden');

    $.ajax({
        url: '/ajax/search-equipment-po',
        method: 'POST',
        data: {
            q: search,
            category_id: currentCategoryFilter,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            renderCatalogGrid(response.items);
        },
        error: function (xhr) {
            console.error('Failed to load catalog', xhr);
            $('#catalog-loader').addClass('hidden');
            // Show error message in grid
            $('#catalog-grid').html(`<div class="col-span-full text-center text-red-500 py-4">Error loading items: ${xhr.statusText}</div>`);
        },
        complete: function () {
            $('#catalog-loader').addClass('hidden');
            $('#catalog-grid').removeClass('opacity-50');
        }
    });
}

function renderCatalogGrid(items) {
    const $grid = $('#catalog-grid');
    $grid.empty();

    if (!items || items.length === 0) {
        $('#catalog-empty').removeClass('hidden');
        return;
    }

    items.forEach(item => {
        // Use a default image if image_url is missing or broken (handled by backend usually, but double safety)
        const imgSrc = item.image_url || '/images/no-image.png';

        const card = `
                <div class="item-card bg-white rounded-xl border border-gray-200 p-3 cursor-pointer relative overflow-hidden group transition-all hover:shadow-lg"
                    onclick='selectCatalogItem(${JSON.stringify(item).replace(/'/g, "&#39;")})' id="item-card-${item.id}">

                    <div class="h-28 sm:h-32 bg-gray-50 rounded-lg mb-2 overflow-hidden flex items-center justify-center relative">
                        <img src="${imgSrc}" class="max-w-full max-h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-300" loading="lazy">
                        <div class="absolute top-1 right-1">
                             <div class="scale-90 origin-top-right">
                                ${item.stock_badge}
                             </div>
                        </div>
                    </div>

                    <h5 class="font-bold text-gray-800 text-xs sm:text-sm leading-tight mb-0.5 line-clamp-2 min-h-[2.5em] tracking-tight" title="${item.name}">${item.name}</h5>
                    <p class="text-[10px] sm:text-xs text-gray-500 font-mono mb-2 truncate">${item.serial}</p>

                    <div class="flex items-center justify-between mt-auto">
                        <span class="text-[10px] font-semibold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded truncate max-w-[70%]">${item.category}</span>
                        <button class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fas fa-cart-plus text-xs"></i>
                        </button>
                    </div>

                    <!-- Selection Overlay Border -->
                    <div class="absolute inset-0 border-2 border-transparent rounded-xl pointer-events-none transition-colors" id="border-${item.id}"></div>
                </div>
            `;
        $grid.append(card);
    });
}

// =============================================================================
// 3. Interaction Logic
// =============================================================================

window.selectCatalogItem = function (item) {
    selectedItem = item;

    // Highlight Card
    $('.item-card').removeClass('ring-2 ring-blue-500 bg-blue-50').addClass('bg-white');
    $(`#item-card-${item.id}`).removeClass('bg-white').addClass('ring-2 ring-blue-500 bg-blue-50');

    // Fill Data (Desktop)
    $('#sel-image').attr('src', item.image_url);
    $('#sel-name').text(item.name);
    $('#sel-serial').text(item.serial);
    $('#sel-stock').html(item.stock_badge);
    $('#sel-quantity').val(1);

    // Fill Data (Mobile)
    $('#mob-sel-image').attr('src', item.image_url);
    $('#mob-sel-name').text(item.name);
    $('#mob-sel-serial').text(item.serial);
    $('#mob-sel-stock').html(item.stock_badge);
    $('#mob-sel-quantity').val(1);

    // Show Panel based on screen size
    if (window.innerWidth < 768) {
        // Mobile: Slide up sheet
        $('#mobile-selection-sheet').removeClass('translate-y-full').addClass('translate-y-0');
        // Add padding to grid to avoid covering
        $('#catalog-grid-container').addClass('pb-80');
    } else {
        // Desktop: Show Side Panel
        $('#selected-item-detail').addClass('hidden');
        $('#active-selection-form').removeClass('hidden');
        $('#selection-panel').removeClass('hidden');
    }
}

window.hideSelectionPanel = function () {
    // Desktop reset
    $('#selected-item-detail').removeClass('hidden');
    $('#active-selection-form').addClass('hidden');

    // Mobile reset
    $('#mobile-selection-sheet').removeClass('translate-y-0').addClass('translate-y-full');
    $('#catalog-grid-container').removeClass('pb-80');

    $('.item-card').removeClass('ring-2 ring-blue-500 bg-blue-50').addClass('bg-white');
    selectedItem = null;
}

window.closeMobileSheet = function () {
    hideSelectionPanel();
}



window.adjustQty = function (delta) {
    // Sync both inputs
    const $desktopInput = $('#sel-quantity');
    const $mobileInput = $('#mob-sel-quantity');

    let val = parseInt($desktopInput.val()) || 1;
    val = Math.max(1, val + delta);

    $desktopInput.val(val);
    $mobileInput.val(val);
}

window.confirmAddCatalogItem = function () {
    if (!selectedItem) return;

    // Get quantity based on active view
    let quantity = 1;
    if (window.innerWidth < 768) {
        quantity = parseInt($('#mob-sel-quantity').val()) || 1;
    } else {
        quantity = parseInt($('#sel-quantity').val()) || 1;
    }

    // Determine Route based on current PO Type or ID
    let url = '';

    if (currentPoId === 'scheduled' || (window.currentQuickAddType === 'scheduled')) {
        url = `/purchase-orders/scheduled/add-item/${selectedItem.id}`;
    } else if (currentPoId === 'urgent' || (window.currentQuickAddType === 'urgent')) {
        url = `/purchase-orders/urgent/add-item/${selectedItem.id}`;
    } else if (currentPoId && !isNaN(currentPoId)) {
        // Specific PO Adding (Legacy/Edit Mode)
        url = `/purchase-orders/${currentPoId}/add-item`;
    } else {
        // Default fallback to Scheduled if unknowns
        console.warn("⚠️ Unknown PO Context, defaulting to Scheduled");
        url = `/purchase-orders/scheduled/add-item/${selectedItem.id}`;
    }


    // Animation/Feedback before AJAX
    let btn;
    if (window.innerWidth < 768) {
        btn = document.getElementById('mob-btn-confirm-add');
    } else {
        btn = document.getElementById('btn-confirm-add');
    }

    if (!btn) {
        // Fallback if not found (unexpected)
        console.error('Confirm button not found, falling back to desktop');
        btn = document.getElementById('btn-confirm-add');
    }

    let originalText = '';
    if (btn) {
        originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังเพิ่ม...';
        btn.disabled = true;
    }

    $.ajax({
        url: url, // Use dynamic URL
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Content-Type': 'application/json'
        },
        data: JSON.stringify({
            equipment_id: selectedItem.id, // Only needed for generic route, redundant for specific but harmless
            quantity: quantity
        }),
        success: function (response) {
            Swal.fire({
                icon: 'success',
                title: 'เพิ่มลงตะกร้าเรียบร้อย',
                text: `${selectedItem.name} จำนวน ${quantity} ชิ้น`,
                timer: 1000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });

            // RELOAD PAGE Removed for continuous add
            // setTimeout(() => window.location.reload(), 500);

            // Continuous Flow:
            // 1. Update current ID if we just got a real one back from controller
            if (response.po_id) {
                // If we were using generic 'scheduled'/'urgent' placeholder, update to real ID
                // This allows refreshItemsList to work correctly
                if (isNaN(currentPoId)) {
                    currentPoId = response.po_id;
                    // Also update global/window state if needed (optional)
                }
                // 2. Refresh the background list
                refreshItemsList(response.po_id);
            }

            hasAddedItems = true; // Mark that we need a reload on close

            // 3. Clear selection and go back to grid to pick next
            hideSelectionPanel();
            $('#sel-quantity').val(1); // Reset quantity
        },
        error: function (xhr) {
            // Parse Error Message
            let msg = 'Failed';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            // Check if it's the "Unset Requester" error
            if (msg.includes('ผู้สั่งอัตโนมัติ')) {
                Swal.fire('แจ้งเตือน', 'กรุณาตั้งค่า "ผู้สั่งอัตโนมัติ" ในเมนูตั้งค่าก่อนทำรายการ', 'warning');
            } else {
                Swal.fire('Error', msg, 'error');
            }
        },
        complete: function () {
            if (btn && originalText) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    });
}

// =============================================================================
// 4. Searching & Filtering
// =============================================================================

window.debounceSearch = function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadCatalogItems();

        // Toggle Clear Button
        const val = $('#catalog-search').val();
        if (val) $('#clear-search-btn').removeClass('hidden');
        else $('#clear-search-btn').addClass('hidden');

    }, 400); // 400ms debounce
}

window.clearCatalogSearch = function () {
    $('#catalog-search').val('');
    $('#clear-search-btn').addClass('hidden');
    loadCatalogItems();
}

window.filterCatalogCategory = function (categoryId) {
    currentCategoryFilter = categoryId;

    // Update Tabs
    $('.category-btn').removeClass('bg-blue-600 text-white shadow-md').addClass('bg-white text-gray-600 hover:bg-gray-50');
    $(`button[data-category="${categoryId}"]`).removeClass('bg-white text-gray-600 hover:bg-gray-50').addClass('bg-blue-600 text-white shadow-md');

    loadCatalogItems();
}


// =============================================================================
// 5. Utilities (Copy from previous logic)
// =============================================================================

function getScheduledPoId() {
    const container = document.querySelector('[id^="po-items-container-"]');
    if (container) {
        const match = container.id.match(/po-items-container-(\d+)/);
        return match ? match[1] : null;
    }
    return null;
}

async function createScheduledPOAndOpen() {
    try {
        const response = await fetch('/purchase-orders/create-scheduled', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();
        if (data.success) openQuickAddModal(data.po_id);
    } catch (err) { console.error(err); }
}

async function createUrgentPOAndOpen() {
    // Similar to above... logic reused
    try {
        const response = await fetch('/purchase-orders/create-urgent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();
        if (data.success) {
            openQuickAddModal(data.po_id);
            setTimeout(() => window.location.reload(), 1500);
        }
    } catch (err) { console.error(err); }
}

async function refreshItemsList(orderId) {
    const container = document.getElementById(`po-items-container-${orderId}`);
    if (!container) {
        // ✅ If container doesn't exist (e.g. newly created PO), reload the page to render it properly
        window.location.reload();
        return;
    }
    try {
        const response = await fetch(`/purchase-orders/${orderId}/items-view`);
        if (response.ok) {
            container.innerHTML = await response.text();
        } else {
            console.error("Failed to refresh items view");
        }
    } catch (e) {
        console.error("Error refreshing items:", e);
    }
}

function loadCategoryFilters() {
    $.get('/api/categories').then(cats => {
        const container = $('#catalog-categories');
        // Remove old dynamic buttons (keep 'All')
        container.find('button:not([data-category="all"])').remove();

        cats.forEach(c => {
            container.append(`
                    <button type="button" data-category="${c.id}" onclick="filterCatalogCategory(${c.id})"
                        class="px-4 py-2 rounded-xl text-sm font-bold bg-white text-gray-600 hover:bg-gray-50 transition-all whitespace-nowrapflex-shrink-0 category-btn border border-gray-100 shadow-sm">
                        ${c.name}
                    </button>
                 `);
        });
    }).fail(function () {
        console.error("Failed to load categories. Endpoint might be 404.");
    });
}

