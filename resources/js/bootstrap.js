import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });


// ✅✅✅ START: GLOBAL AJAX SETUP ✅✅✅

import $ from 'jquery';
window.$ = window.jQuery = $;

// This code sets up a global error handler for all jQuery AJAX requests in the application.
$(function() {
    $.ajaxSetup({
        headers: {
            // Automatically add the CSRF token to all AJAX requests.
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        statusCode: {
            // Handler for "Unauthorized" error.
            401: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่ได้รับอนุญาต',
                    text: 'คุณไม่มีสิทธิ์ในการดำเนินการนี้',
                });
            },
            // Handler for "Forbidden" error.
            403: function() {
                 Swal.fire({
                    icon: 'error',
                    title: 'การเข้าถึงถูกปฏิเสธ',
                    text: 'คุณไม่มีสิทธิ์ในการเข้าถึงส่วนนี้',
                });
            },
            // ✅✅✅ MODIFIED THIS SECTION ✅✅✅
            // Handler for "Page Expired" (CSRF Token Mismatch / Session Timeout)
            419: function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'การเชื่อมต่อหมดอายุ',
                    text: 'กรุณาเข้าสู่ระบบอีกครั้งเพื่อดำเนินการต่อ',
                    confirmButtonText: 'ไปที่หน้า Login',
                    allowOutsideClick: false, // Prevents closing the modal by clicking outside
                    allowEscapeKey: false, // Prevents closing with the Escape key
                }).then(() => {
                    // Automatically redirect the user to the login page.
                    window.location.href = '/login';
                });
            }
        }
    });
});
// ✅✅✅ END: GLOBAL AJAX SETUP ✅✅✅