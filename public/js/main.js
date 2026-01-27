/**
 * main.js (Core Module - Full Version)
 * Handles shared, global functionality like API requests, modals,
 * toasts, and basic UI setup.
 *
 * Corrected version: Replaced `window.onload` with `addEventListener` to prevent script conflicts.
 */

document.addEventListener('DOMContentLoaded', function () {
    // This part is correct and remains unchanged.
    setupEventListeners();
    updateNotifications();
    initPopularTicker(); // Start Ticker
});


// ✅✅✅ START: CORRECTED SECTION ✅✅✅
// Use DOMContentLoaded for faster perceived load time (don't wait for all images/scripts)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        handleLoadingScreen();
    });
} else {
    handleLoadingScreen();
}
// ✅✅✅ END: CORRECTED SECTION ✅✅✅

// ==================================================================
// --- GLOBAL SESSION TIMEOUT GUARD ---
// Intercepts all Fetch and jQuery AJAX calls to detect redirects to the login page.
// This prevents the "Nested Login Page" issue when session expires.
// ==================================================================
(function () {
    // 1. Intercept Fetch API
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
        try {
            const response = await originalFetch(...args);

            // Check if response URL has changed to login (Redirected)
            if (response.redirected && response.url.includes('/login')) {
                console.warn('[Session Guard] Session expired detected via Fetch (Redirect). Reloading...');
                window.location.href = '/login';
                return new Promise(() => { }); // Halt promise chain
            }

            // ✅ Check for 419 (CSRF) or 401 (Unauthorized)
            if (response.status === 419 || response.status === 401) {
                console.warn(`[Session Guard] Session expired detected via Fetch (Status ${response.status}). Redirecting to login...`);

                // Optional: Show Alert before redirecting? User said "it becomes error".
                // Better to just redirect smoothly or show a quick alert.
                // Let's use SweetAlert if available, otherwise redirect.
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'เซสชั่นหมดอายุ',
                        text: 'กรุณาเข้าสู่ระบบใหม่',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = '/login';
                    });
                    return new Promise(() => { }); // Halt
                } else {
                    window.location.href = '/login';
                    return new Promise(() => { }); // Halt
                }
            }

            return response;
        } catch (error) {
            throw error;
        }
    };

    // 2. Intercept jQuery AJAX (if available)
    if (typeof $ !== 'undefined') {
        $(document).ajaxComplete(function (event, xhr, settings) {
            // Check responseURL (standard XHR property)
            if (xhr.responseURL && xhr.responseURL.includes('/login')) {
                console.warn('[Session Guard] Session expired detected via jQuery. Reloading...');
                window.location.reload();
            }
            // Fallback: Check status code 401/419
            if (xhr.status === 401 || xhr.status === 419) {
                console.warn('[Session Guard] Session expired (401/419). Reloading...');
                window.location.reload();
            }
        });
    }
})();


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
    // ✅ Fix: Use Base URL from Meta Tag to support sub-directories
    const baseUrlMeta = document.querySelector('meta[name="app-base-url"]');
    const baseUrl = baseUrlMeta ? baseUrlMeta.getAttribute('content') : '';
    // Ensure no double slash
    const url = `${baseUrl.replace(/\/$/, '')}/ajax-handler`;

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
    document.addEventListener('click', function (event) {
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


        // Add Click Listener to Mark Read
        const btnWrapper = document.getElementById('notifications-button-wrapper');
        const btn = btnWrapper ? btnWrapper.querySelector('button') : null;
        if (btn && !btn.hasAttribute('data-read-listener')) {
            btn.setAttribute('data-read-listener', 'true');
            btn.addEventListener('click', () => {
                countEl.classList.add('hidden'); // Immediate UI update
                apiRequest('mark_notifications_read');
            });
        }

        // Add Clear All Listener
        const clearBtn = document.getElementById('clear-notifs-btn');
        if (clearBtn && !clearBtn.hasAttribute('data-clear-listener')) {
            clearBtn.setAttribute('data-clear-listener', 'true');
            clearBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                // No confirm needed for better UX, or maybe yes? User asked to clear.
                // Let's just do it.
                await apiRequest('clear_notifications');
                // Refresh immediately
                updateNotifications();
            });
        }

        data.notifications.forEach(notif => {
            let icon = 'fa-info-circle text-gray-400';
            let bgClass = 'bg-gray-100';

            if (notif.icon) {
                // ✅ Database Notification (Has explicit icon)
                icon = notif.icon;

                // Assign colors based on type
                if (notif.type === 'success') {
                    icon += ' text-green-500';
                    bgClass = 'bg-green-50';
                } else if (notif.type === 'error' || notif.type === 'critical') {
                    icon += ' text-red-500';
                    bgClass = 'bg-red-50';
                } else if (notif.type === 'warning') {
                    icon += ' text-yellow-500';
                    bgClass = 'bg-yellow-50';
                } else {
                    icon += ' text-blue-500';
                    bgClass = 'bg-blue-50';
                }

            } else if (notif.type === 'low_stock') {
                icon = 'fa-exclamation-triangle text-orange-500';
                bgClass = 'bg-orange-50';
            } else if (notif.type === 'out_of_stock') {
                icon = 'fa-times-circle text-red-500';
                bgClass = 'bg-red-50';
            } else if (notif.type === 'pending_approval') {
                icon = 'fa-clock text-blue-500';
                bgClass = 'bg-blue-50';
            }

            const item = document.createElement('a');
            item.href = notif.url || '#';
            item.className = 'flex items-start p-3 mx-2 my-1 rounded-xl transition-all hover:bg-gray-50 group border border-transparent hover:border-blue-50';

            // ✅ Add Click Handler to Mark Single Item as Read
            item.onclick = (e) => markNotificationAsRead(e, notif.id, notif.url);

            item.innerHTML = `
                <div class="w-10 h-10 ${bgClass} rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="flex-1 ml-3 min-w-0">
                    <p class="font-medium text-sm text-gray-700 group-hover:text-blue-600 transition-colors line-clamp-2">
                        ${notif.message}
                        ${!notif.is_read ? '<span class="inline-block w-2 h-2 ml-1 bg-red-500 rounded-full"></span>' : ''}
                    </p>
                    <p class="text-[10px] text-gray-400 mt-0.5">แจ้งเตือนระบบ</p>
                </div>
            `;
            listEl.appendChild(item);
        });
    } else {
        countEl.classList.add('hidden');
        listEl.innerHTML = '<p class="text-center text-gray-500 p-8">ไม่มีการแจ้งเตือนใหม่</p>';
    }
}


async function initPopularTicker() {
    const el = document.getElementById('ticker-content');
    if (!el) return;

    try {
        const res = await apiRequest('get_popular_items');
        if (res.success && res.items.length > 0) {
            let index = 0;
            const updateText = () => {
                const item = res.items[index];
                el.style.opacity = '0';
                el.style.transform = 'translateY(10px)';

                setTimeout(() => {
                    if (item.type === 'recent') {
                        // New Recent Access Format
                        el.innerHTML = `<span class="font-bold text-gray-700">${item.user}:</span> <span class="text-gray-600">${item.action}</span> <span class="font-bold text-indigo-600">${item.name}</span> <span class="text-xs text-gray-400 ml-1">(${item.time})</span>`;
                    } else {
                        // Fallback (or if reverted to count)
                        el.innerHTML = `<span class="font-bold text-gray-700">${item.name}</span> <span class="text-xs text-gray-400 ml-1">(${item.count} ครั้ง)</span>`;
                    }

                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                    index = (index + 1) % res.items.length;
                }, 300);
            };

            updateText(); // Initial run
            setInterval(updateText, 4000); // Change every 4 seconds
        } else {
            el.innerText = 'ระบบพร้อมใช้งาน';
        }
    } catch (e) {
        console.error('Ticker Error:', e);
        el.innerText = 'MM Stock Pro';
    }
}

async function toggleAutoConfirmSystem() {
    const btn = document.getElementById('auto-confirm-btn');
    const ping = document.getElementById('auto-confirm-ping');
    const statusText = document.getElementById('auto-confirm-status-text');
    const icon = btn.querySelector('i');
    const tooltipTitle = btn.parentElement.querySelector('.font-bold');

    if (!btn) return;

    // Optimistic UI Update (Toggle visually first)
    // Actually, safer to wait for server response to ensure session is set.
    // Use a loading state if needed, but let's just wait.

    try {
        const res = await apiRequest('toggle_auto_confirm');
        if (res.success) {
            const isEnabled = res.enabled;

            if (isEnabled) {
                // Enabled State
                btn.className = "relative p-3 transition-all rounded-2xl button-soft bg-yellow-50 hover:bg-yellow-100 text-yellow-600 animate-pulse-soft";
                ping.classList.remove('hidden');
                icon.classList.remove('grayscale');
                statusText.innerText = "เปิดใช้งานอยู่ (อนุมัติทันที)";
                tooltipTitle.className = "font-bold text-yellow-700 mb-1";
                btn.parentElement.title = "คลิกเพื่อปิดระบบอนุมัติอัตโนมัติ";
                showToast('เปิดระบบยืนยันอัตโนมัติแล้ว', 'success');
            } else {
                // Disabled State
                btn.className = "relative p-3 transition-all rounded-2xl button-soft bg-gray-100 hover:bg-gray-200 text-gray-400";
                ping.classList.add('hidden');
                icon.classList.add('grayscale');
                statusText.innerText = "ปิดใช้งาน (ต้องอนุมัติเอง)";
                tooltipTitle.className = "font-bold text-gray-500 mb-1";
                btn.parentElement.title = "คลิกเพื่อเปิดระบบอนุมัติอัตโนมัติ";
                showToast('ปิดระบบยืนยันอัตโนมัติแล้ว', 'info');
            }
        } else {
            showToast(res.message || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (error) {
        console.error('Toggle Error:', error);
        showToast('การเชื่อมต่อขัดข้อง', 'error');
    }
}

async function toggleUserReturnSystem() {
    const btn = document.getElementById('user-return-toggle-btn');
    const ping = document.getElementById('user-return-ping');
    const statusText = document.getElementById('user-return-status-text');
    const icon = btn.querySelector('i');
    const tooltipTitle = btn.parentElement.querySelector('.font-bold');

    if (!btn) return;

    try {
        const res = await apiRequest('toggle_user_return_request');
        if (res.success) {
            const isEnabled = res.enabled;

            if (isEnabled) {
                // Enabled State
                btn.className = "relative p-3 transition-all rounded-2xl button-soft bg-purple-50 hover:bg-purple-100 text-purple-600";
                ping.classList.remove('hidden');
                icon.classList.remove('grayscale');
                statusText.innerText = "เปิดใช้งานอยู่";
                tooltipTitle.className = "font-bold text-purple-700 mb-1";
                btn.parentElement.title = "คลิกเพื่อปิดระบบขอคืนอุปกรณ์";
                showToast('เปิดระบบขอคืนอุปกรณ์แล้ว', 'success');
            } else {
                // Disabled State
                btn.className = "relative p-3 transition-all rounded-2xl button-soft bg-gray-100 hover:bg-gray-200 text-gray-400";
                ping.classList.add('hidden');
                icon.classList.add('grayscale');
                statusText.innerText = "ปิดใช้งาน";
                tooltipTitle.className = "font-bold text-gray-500 mb-1";
                btn.parentElement.title = "คลิกเพื่อเปิดระบบขอคืนอุปกรณ์";
                showToast('ปิดระบบขอคืนอุปกรณ์แล้ว', 'info');
            }
        } else {
            showToast(res.message || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (error) {
        console.error('Toggle Error:', error);
        showToast('การเชื่อมต่อขัดข้อง', 'error');
    }
}

// ✅ Function to Mark Single Notification as Read
async function markNotificationAsRead(event, id, url) {
    event.preventDefault(); // Stop immediate navigation

    // Optimistic UI: Hide the red dot immediately
    const target = event.target.closest('a');
    if (target) {
        const dot = target.querySelector('.bg-red-500');
        if (dot) dot.remove();
        target.classList.add('opacity-50'); // Feedback
    }

    try {
        // Call Backend
        await apiRequest('mark_notification_read', { id: id });
    } catch (e) {
        console.error("Failed to mark read", e);
    }

    // Navigate manually after action
    if (url && url !== '#') {
        window.location.href = url;
    }
}