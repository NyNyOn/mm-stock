document.addEventListener('DOMContentLoaded', function () {
    // --- ตัวแปรหลักสำหรับ Chart และ Filter ---
    const yearSelect = document.getElementById('chartYearSelect');
    const categorySelect = document.getElementById('chartCategorySelect');
    const equipmentSelect = $('#chartEquipmentSelect'); // ใช้ jQuery กับ Select2
    const seriesToggles = document.querySelectorAll('.chart-series-checkbox');
    const chartCanvas = document.getElementById('mainDashboardChart');

    // --- ตัวแปรสำหรับ Countdown & Settings ---
    const countdownDisplays = document.querySelectorAll('.stock-countdown-display');
    const SETTINGS_KEY = 'dashboardChartSettings';
    let dashboardChart = null;

    // 🔥🔥 ตัวแปร Global สำหรับค่าเริ่มต้นสี (ต้องตรงกับ DEFAULT_CHART_COLORS ใน blade)
    const DEFAULT_CHART_COLORS = {
        received: { start: '#4ade80', end: '#14532d', border: '#15803d' },
        withdrawn: { start: '#fca5a5', end: '#991b1b', border: '#ef4444' },
        borrowed: { start: '#fde047', end: '#713f12', border: '#a16207' },
        returned: { start: '#93c5fd', end: '#1e3a8a', border: '#3b82f6' }
    };

    // ============================================================
    // 1. POPUP ALERT (แจ้งเตือนเมื่อเข้าหน้าเว็บ)
    // ============================================================
    // ✅ เพิ่มการเช็คสิทธิ์ (Admin/IT/ID9)
    const canNotify = (typeof window.canNotifyStock !== 'undefined' && window.canNotifyStock === true);

    if (canNotify) {
        if (typeof window.lockedStockCount !== 'undefined' && window.lockedStockCount > 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: '⚠️ ระงับการเบิกจ่าย!',
                    html: `
                        <p class="text-gray-600">มีหมวดหมู่อุปกรณ์ <b>${window.lockedStockCount} รายการ</b> ที่เลยกำหนดนับสต๊อกแล้ว</p>
                        <p class="text-sm text-red-500 mt-2">ระบบจะระงับการทำรายการจนกว่าจะมีการตรวจนับ</p>
                    `,
                    confirmButtonText: 'ไปที่หน้าตรวจนับสต๊อก',
                    confirmButtonColor: '#ef4444',
                    showCancelButton: true,
                    cancelButtonText: 'รับทราบ',
                    cancelButtonColor: '#6b7280',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '/stock-checks/create';
                    }
                });
            }
        }
        // ✅ เพิ่ม Alert สำหรับ 90 วัน (Warning)
        else if (typeof window.warningStockCount !== 'undefined' && window.warningStockCount > 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: '⏳ ถึงรอบตรวจนับสต๊อก',
                    html: `
                        <p class="text-gray-600">มีหมวดหมู่อุปกรณ์ <b>${window.warningStockCount} รายการ</b> ครบกำหนด 90 วัน</p>
                        <p class="text-sm text-orange-500 mt-2">กรุณาดำเนินการภายใน 15 วัน ก่อนระบบจะระงับการเบิก</p>
                    `,
                    confirmButtonText: 'รับทราบ / ไปตรวจนับ',
                    confirmButtonColor: '#f59e0b',
                    showCancelButton: true,
                    cancelButtonText: 'ไว้ทีหลัง',
                    cancelButtonColor: '#9ca3af'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '/stock-checks/create';
                    }
                });
            }
        }
    }

    // ============================================================
    // 2. COUNTDOWN TIMER LOGIC
    // ============================================================
    if (countdownDisplays.length > 0) {

        const updateAllTimers = () => {
            const now = new Date().getTime(); // เวลาปัจจุบันของ Browser (ms)

            countdownDisplays.forEach(display => {
                const targetTimestamp = parseInt(display.getAttribute('data-target'));

                if (!targetTimestamp || isNaN(targetTimestamp) || targetTimestamp === 0) {
                    display.innerHTML = '<span class="text-gray-400 text-xs">- ไม่ระบุวัน -</span>';
                    return;
                }

                const distance = targetTimestamp - now;
                const absDist = Math.abs(distance);

                const days = Math.floor(absDist / (1000 * 60 * 60 * 24));
                const hours = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((absDist % (1000 * 60)) / 1000);

                let mainColor, subColor, prefixText, icon;

                if (distance < 0) {
                    // 🔴 กรณีเลยกำหนด (Overdue)
                    mainColor = '#dc2626';
                    subColor = '#fca5a5';
                    prefixText = 'เลยมาแล้ว';
                    icon = '<i class="fas fa-exclamation-circle animate-pulse"></i>';
                } else if (days <= 15) {
                    // 🟠 กรณีใกล้ถึง (Warning)
                    mainColor = '#d97706';
                    subColor = '#fcd34d';
                    prefixText = 'เหลืออีก';
                    icon = '⚠️';
                } else {
                    // 🟢 กรณีปกติ (Safe)
                    mainColor = '#059669';
                    subColor = '#6ee7b7';
                    prefixText = 'เหลืออีก';
                    icon = '⏳';
                }

                // Render HTML
                display.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 11px; color: ${mainColor}; line-height: 1;">
                        <span style="font-weight: bold; margin-right: 2px;">${icon} ${prefixText}</span>
                        
                        <div style="text-align: center;">
                            <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${days}</span>
                            <span style="font-size: 9px; color: ${subColor};">วัน</span>
                        </div>

                        <div style="text-align: center;">
                            <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${hours.toString().padStart(2, '0')}</span>
                            <span style="font-size: 9px; color: ${subColor};">ชม.</span>
                        </div>

                        <div style="text-align: center;">
                            <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${minutes.toString().padStart(2, '0')}</span>
                            <span style="font-size: 9px; color: ${subColor};">น.</span>
                        </div>

                        <div style="text-align: center;">
                            <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${seconds.toString().padStart(2, '0')}</span>
                            <span style="font-size: 9px; color: ${subColor};">วิ.</span>
                        </div>
                    </div>
                `;
            });
        };

        // เรียกฟังก์ชันทันที 1 ครั้งเพื่อไม่ให้รอ 1 วินาทีแรก
        updateAllTimers();
        // ตั้ง Loop ทำงานทุก 1 วินาที
        setInterval(updateAllTimers, 1000);
    }

    // ============================================================
    // 3. CHART & FILTER LOGIC (Modern Gradient Design)
    // ============================================================
    if (chartCanvas) {
        Chart.register(ChartDataLabels);

        // Helper: Create Gradient
        function createGradient(ctx, colorStart, colorEnd) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400); // 400px height for canvas
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        }

        // 🔥 ดึงค่าสีจาก localStorage หรือใช้ค่าเริ่มต้น
        const getChartColors = () => {
            const savedColors = localStorage.getItem('customChartColors');
            return savedColors ? JSON.parse(savedColors) : DEFAULT_CHART_COLORS;
        };


        // Functions for Chart Settings (Save/Load)
        function saveSettings() {
            const settings = {
                year: yearSelect.value,
                categoryId: categorySelect.value,
                equipmentId: equipmentSelect.val(),
                equipmentText: equipmentSelect.find('option:selected').text(),
                selectedSeries: Array.from(seriesToggles).filter(checkbox => checkbox.checked).map(checkbox => checkbox.value)
            };
            localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
        }

        function loadSettings() {
            const savedSettings = JSON.parse(localStorage.getItem(SETTINGS_KEY));
            if (savedSettings) {
                if (savedSettings.year) yearSelect.value = savedSettings.year;
                if (savedSettings.categoryId) categorySelect.value = savedSettings.categoryId;
                if (savedSettings.equipmentId && savedSettings.equipmentText) {
                    const option = new Option(savedSettings.equipmentText, savedSettings.equipmentId, true, true);
                    equipmentSelect.append(option).trigger('change');
                }
                if (savedSettings.selectedSeries) {
                    seriesToggles.forEach(checkbox => {
                        checkbox.checked = savedSettings.selectedSeries.includes(checkbox.value);
                    });
                }
            }
        }

        // 🔥 ทำให้ fetchAndRenderChart เป็น Global Function เพื่อให้ Blade View เรียกใช้ได้
        window.fetchAndRenderChart = function () {
            const year = yearSelect.value;
            const categoryId = categorySelect.value;
            const equipmentId = equipmentSelect.val();
            const chartColors = getChartColors(); // 🔥 ดึงสีล่าสุด

            // จัดการ UI ของปุ่ม Toggle (ทำให้ปุ่มจางเมื่อไม่เลือก)
            seriesToggles.forEach(chk => {
                const label = chk.closest('label');
                if (chk.checked) {
                    label.classList.remove('opacity-40', 'grayscale');
                    label.classList.add('shadow-inner', 'bg-opacity-100');
                } else {
                    label.classList.add('opacity-40', 'grayscale');
                    label.classList.remove('shadow-inner', 'bg-opacity-100');
                }
            });

            const selectedKeys = Array.from(seriesToggles).filter(c => c.checked).map(c => c.value);
            const url = `/ajax/dashboard-charts?year=${year}&category_id=${categoryId || ''}&equipment_id=${equipmentId || ''}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    const ctx = chartCanvas.getContext('2d');
                    const datasets = [];
                    let maxDataValue = 0; // Initialize max value tracker

                    // สร้าง Dataset แยก Object กันเพื่อให้แสดงหลายแท่ง (Grouped Bar Chart)
                    selectedKeys.forEach(key => {
                        if (data.datasets[key]) {
                            const theme = chartColors[key];

                            // 🔥 คำนวณค่าสูงสุดจากทุก Dataset
                            const currentData = data.datasets[key].data.map(Number);
                            const currentMax = Math.max(...currentData);
                            if (currentMax > maxDataValue) {
                                maxDataValue = currentMax;
                            }

                            datasets.push({
                                label: data.datasets[key].label,
                                data: data.datasets[key].data,
                                backgroundColor: createGradient(ctx, theme.start, theme.end),
                                borderColor: theme.border,
                                borderWidth: 1,
                                borderRadius: 5,
                                barPercentage: 0.7,      // ความกว้างของแท่ง (0-1)
                                categoryPercentage: 0.8, // ระยะห่างระหว่างกลุ่ม (0-1)
                                type: 'bar'
                            });
                        }
                    });

                    // 🔥 Logic: ขยายแกน Y ให้สูงกว่าค่าสูงสุด 1 ช่องเสมอ (Max Data + Buffer)
                    let yAxisMax = undefined;
                    if (maxDataValue > 0) {
                        if (maxDataValue >= 10) {
                            // ถ้าค่ามาก ให้เพิ่ม 15% (เพื่อให้ดูไม่เต็ม) และปัดขึ้นเป็นจำนวนเต็ม
                            yAxisMax = Math.ceil(maxDataValue * 1.15);
                        } else {
                            // ถ้าค่าน้อย ให้เพิ่ม 2 หน่วย
                            yAxisMax = maxDataValue + 2;
                        }
                    } else {
                        // ถ้าไม่มีข้อมูล ให้กำหนด Max เป็น 10
                        yAxisMax = 10;
                    }


                    if (dashboardChart) dashboardChart.destroy();

                    dashboardChart = new Chart(chartCanvas, {
                        type: 'bar', // กำหนดเป็น bar chart
                        data: { labels: data.labels, datasets: datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index', // โหมดที่ทำให้แสดงข้อมูลทุกแท่งในเดือนที่ชี้
                                intersect: false
                            },
                            scales: {
                                x: {
                                    stacked: false, // ต้องเป็น FALSE เพื่อให้แท่งแยกกัน
                                    grid: { display: false }
                                },
                                y: {
                                    stacked: false, // ต้องเป็น FALSE
                                    beginAtZero: true,
                                    max: yAxisMax, // 🔥 ใช้ค่าที่คำนวณไว้
                                    ticks: { precision: 0 },
                                    grid: { color: '#f3f4f6' }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                datalabels: {
                                    // 🔥 การตั้งค่า Data Labels
                                    anchor: 'end',
                                    align: 'top',
                                    offset: 8,     // เลื่อนตัวเลขขึ้นมาเพื่อไม่ให้ติดขอบ
                                    clip: false,   // ไม่อนุญาตให้ตัวเลขถูกตัดเมื่ออยู่ติดขอบ
                                    // 🔥 กำหนดสีตัวเลขให้เป็นสีเดียวกับขอบแท่ง (borderColor)
                                    color: (ctx) => {
                                        return ctx.dataset.borderColor;
                                    },
                                    font: { weight: 'bold', size: 10 },
                                    formatter: (val) => val > 0 ? val : '' // แสดงเฉพาะค่าที่มากกว่า 0
                                },
                                tooltip: {
                                    padding: 12,
                                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                                    titleColor: '#111827',
                                    bodyColor: '#4b5563',
                                    borderColor: '#e5e7eb',
                                    borderWidth: 1,
                                    usePointStyle: true
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                })
                .catch(error => console.error('Error fetching chart data:', error));

            saveSettings();
        }

        // Initialize Select2 (สำหรับค้นหาอุปกรณ์)
        if (equipmentSelect.length) {
            equipmentSelect.select2({
                placeholder: '🔍 ค้นหาอุปกรณ์เฉพาะเจาะจง...',
                theme: "classic",
                width: '100%',
                allowClear: true,
                ajax: {
                    url: "/ajax/search-equipment",
                    dataType: 'json',
                    delay: 250,
                    processResults: function (data) { return { results: data.results }; },
                    cache: true
                }
            });
        }

        // Event Listeners
        yearSelect.addEventListener('change', window.fetchAndRenderChart);
        categorySelect.addEventListener('change', window.fetchAndRenderChart);
        equipmentSelect.on('change', window.fetchAndRenderChart);
        seriesToggles.forEach(toggle => { toggle.addEventListener('change', window.fetchAndRenderChart); });

        // Init
        loadSettings();
        window.fetchAndRenderChart();
    }
});