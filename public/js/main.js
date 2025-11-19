/**
 * main.js (Core Module - Full Version)
 * Handles shared, global functionality like API requests, modals,
 * toasts, and basic UI setup.
 *
 * Corrected version: Replaced `window.onload` with `addEventListener` to prevent script conflicts.
 */

document.addEventListener('DOMContentLoaded', function() {
    // This part is correct and remains unchanged.
    setupEventListeners();
    updateNotifications();
});


// ✅✅✅ START: CORRECTED SECTION ✅✅✅
// We now use addEventListener('load', ...) which is the correct and safe way
// to handle the window load event without overwriting other scripts.
window.addEventListener('load', function() {
    handleLoadingScreen();
});
// ✅✅✅ END: CORRECTED SECTION ✅✅✅


function setupEventListeners() {
    const mobileOverlay = document.getElementById('mobile-overlay');
    if (mobileOverlay) mobileOverlay.addEventListener('click', toggleSidebar);

    setupCollapsibleSettingsMenu();
    setupDropdownToggles();
}

// ==================================================================
// --- CORE API COMMUNICATION (Unchanged) ---
// ==================================================================

async function apiRequest(action, data = {}) {
    const url = '/ajax-handler';
    const options = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: new URLSearchParams({ action, ...data })
    };
    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            console.error(`HTTP error! Status: ${response.status}`, await response.text());
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error(`API Request Error (Action: ${action}):`, error);
        showToast('การเชื่อมต่อล้มเหลว', 'error');
        return { success: false, message: 'การเชื่อมต่อล้มเหลว' };
    }
}

// ==================================================================
// --- INITIALIZATION & UI SETUP (All other functions are unchanged) ---
// ==================================================================

function handleLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    const loadingText = document.getElementById('loading-text');

    if (!loadingScreen || !loadingText) return;

    if (sessionStorage.getItem('hasLoadedOnce')) {
        loadingScreen.style.display = 'none';
        return;
    }

    const steps = [
        "กำลังเตรียมข้อมูล...",
        "กำลังโหลดส่วนประกอบ...",
        "เชื่อมต่อฐานข้อมูล...",
        "ตรวจสอบสิทธิ์ผู้ใช้...",
        "กำลังจัดเรียงหน้าเว็บ...",
        "เกือบเสร็จแล้ว..."
    ];
    let stepIndex = 0;

    const interval = setInterval(() => {
        stepIndex = (stepIndex + 1) % steps.length;
        loadingText.textContent = steps[stepIndex];
    }, 800);

    // This function is now correctly triggered by the 'load' event listener at the top.
    clearInterval(interval);
    loadingText.textContent = 'โหลดสำเร็จ!';

    setTimeout(() => {
        loadingScreen.classList.add('fade-out');
        setTimeout(() => {
            loadingScreen.style.display = 'none';
            sessionStorage.setItem('hasLoadedOnce', 'true');
        }, 500);
    }, 500);
}


function setupCollapsibleSettingsMenu() {
    const settingsToggleBtn = document.getElementById('settings-toggle-btn');
    const settingsSubmenu = document.getElementById('settings-submenu');
    const settingsChevron = document.getElementById('settings-chevron');

    if (!settingsToggleBtn || !settingsSubmenu || !settingsChevron) return;

    const openMenu = () => {
        settingsSubmenu.style.maxHeight = settingsSubmenu.scrollHeight + "px";
        settingsChevron.classList.add('rotate-180');
    };
    const closeMenu = () => {
        settingsSubmenu.style.maxHeight = "0px";
        settingsChevron.classList.remove('rotate-180');
    };

    const isSettingsPage = settingsSubmenu.querySelector('.active-nav') !== null;
    if (sessionStorage.getItem('settingsMenuOpen') === 'true' || isSettingsPage) {
        setTimeout(openMenu, 50);
    } else {
        settingsSubmenu.style.maxHeight = "0px";
    }

    settingsToggleBtn.addEventListener('click', () => {
        const isOpen = settingsSubmenu.style.maxHeight !== "0px";
        if (isOpen) {
            closeMenu();
            sessionStorage.setItem('settingsMenuOpen', 'false');
        } else {
            openMenu();
            sessionStorage.setItem('settingsMenuOpen', 'true');
        }
    });
}

function setupDropdownToggles() {
    document.addEventListener('click', function(event) {
        const isNotificationsButton = document.getElementById('notifications-button-wrapper')?.contains(event.target);
        const isProfileButton = document.getElementById('profile-button-wrapper')?.contains(event.target);

        if (!isNotificationsButton) closeDropdown('notifications-dropdown');
        if (!isProfileButton) closeDropdown('profile-dropdown');
    });
}

// All other helper functions (showModal, closeModal, showToast, etc.) remain exactly the same.
// ... (The rest of your functions from main.js are preserved here) ...

function showLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        loadingScreen.style.display = 'flex';
        setTimeout(() => { loadingScreen.style.opacity = '1'; }, 10);
    }
}

function hideLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        loadingScreen.style.opacity = '0';
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

function closeDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    const colors = { success: 'green', error: 'red', info: 'blue', warning: 'orange' };

    const toast = document.createElement('div');
    toast.className = `notification-soft flex items-center p-4 rounded-2xl shadow-lg animate-fade-in border-l-4 border-${colors[type]}-400`;
    toast.innerHTML = `
        <i class="fas ${icons[type]} text-2xl text-${colors[type]}-500 mr-4"></i>
        <div class="flex-1">
            <p class="font-bold text-gray-800">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">&times;</button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function showConfirmationModal(message, onConfirm) {
    const modal = document.getElementById('confirmation-modal');
    if (!modal) return;
    const messageEl = document.getElementById('confirmation-message');
    const confirmBtn = document.getElementById('confirmation-confirm-btn');
    const cancelBtn = document.getElementById('confirmation-cancel-btn');
    if (!messageEl || !confirmBtn || !cancelBtn) return;
    messageEl.innerHTML = message;
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    newConfirmBtn.addEventListener('click', () => {
        onConfirm();
        closeModal('confirmation-modal');
    });
    cancelBtn.onclick = () => closeModal('confirmation-modal');
    showModal('confirmation-modal');
}

function confirmAndSubmitForm(event, formId, title, text) {
    event.preventDefault();
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById(formId);
            if (form) {
                Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });
                form.submit();
            }
        }
    })
}

function showLargeImage(imageUrl) {
    const modal = document.getElementById('image-viewer-modal');
    const img = document.getElementById('image-viewer-img');
    if (modal && img) {
        img.src = imageUrl;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeLargeImage() {
    const modal = document.getElementById('image-viewer-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function updateNotifications() {
    const countEl = document.getElementById('notification-count');
    const listEl = document.getElementById('notifications-list');
    if (!countEl || !listEl) return;

    const data = await apiRequest('get_notifications');
    if (data.success && data.count > 0) {
        countEl.textContent = data.count;
        countEl.classList.remove('hidden');
        listEl.innerHTML = '';
        data.notifications.forEach(notif => {
            const icon = notif.type === 'low_stock' ? 'fa-exclamation-triangle text-orange-500' : 'fa-calendar-times text-purple-500';
            const item = document.createElement('a');
            item.href = `/equipment?search=${notif.id}`;
            item.className = 'flex items-start p-4 space-x-4 transition-colors hover:bg-gray-50';
            item.innerHTML = `
                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center"><i class="fas ${icon}"></i></div>
                <div class="flex-1"><p class="font-medium text-sm text-gray-800">${notif.message}</p></div>
            `;
            listEl.appendChild(item);
        });
    } else {
        countEl.classList.add('hidden');
        listEl.innerHTML = '<p class="text-center text-gray-500 p-8">ไม่มีการแจ้งเตือนใหม่</p>';
    }
}