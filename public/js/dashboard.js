document.addEventListener('DOMContentLoaded', function () {
    // --- ตัวแปรหลักสำหรับ Chart และ Filter ---
    const yearSelect = document.getElementById('chartYearSelect');
    const categorySelect = document.getElementById('chartCategorySelect');
    const equipmentSelect = $('#chartEquipmentSelect'); // ใช้ jQuery กับ Select2
    const seriesToggles = document.querySelectorAll('.chart-series-checkbox');
    const chartCanvas = document.getElementById('mainDashboardChart');
    
    // --- ตัวแปรสำหรับ Countdown ---
    const countdownDisplays = document.querySelectorAll('.stock-countdown-display');
    const SETTINGS_KEY = 'dashboardChartSettings'; 
    let dashboardChart = null;

    // ============================================================
    // 1. POPUP ALERT (แจ้งเตือนเมื่อเข้าหน้าเว็บ)
    // ============================================================
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

    // ============================================================
    // 2. COUNTDOWN TIMER LOGIC (Logic ที่ถูกต้อง 100%)
    // ============================================================
    if (countdownDisplays.length > 0) {
        
        const updateAllTimers = () => {
            const now = new Date().getTime(); // เวลาปัจจุบันของ Browser (ms)

            countdownDisplays.forEach(display => {
                // รับค่า Timestamp ที่ส่งมาจาก PHP (ค่านี้ต้องเป็น Static ห้ามเป็น now() จาก PHP)
                const targetTimestamp = parseInt(display.getAttribute('data-target'));
                
                // ตรวจสอบความถูกต้องของข้อมูล
                if (!targetTimestamp || isNaN(targetTimestamp) || targetTimestamp === 0) {
                    display.innerHTML = '<span class="text-gray-400 text-xs">- ไม่ระบุวัน -</span>';
                    return;
                }

                // คำนวณความต่างของเวลา (Target - Now)
                // - ถ้าเป็นบวก (+) แปลว่ายังไม่ถึงกำหนด (Remaining)
                // - ถ้าเป็นลบ (-) แปลว่าเลยกำหนดแล้ว (Overdue/Elapsed)
                const distance = targetTimestamp - now;
                
                // ใช้ค่าสัมบูรณ์ (Absolute) ในการคำนวณวัน/เวลา เพื่อให้ได้ระยะห่างที่เป็นบวกเสมอ
                // Logic นี้จะทำให้:
                // 1. ถ้านับถอยหลัง: distance ลดลง -> absDist ลดลง -> ตัวเลขลดลง
                // 2. ถ้าเลยกำหนด: distance ติดลบมากขึ้น -> absDist เพิ่มขึ้น -> ตัวเลขเพิ่มขึ้น (นับเดินหน้า)
                const absDist = Math.abs(distance);

                const days = Math.floor(absDist / (1000 * 60 * 60 * 24));
                const hours = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((absDist % (1000 * 60)) / 1000);

                let mainColor, subColor, prefixText, icon;

                // --- แยกเงื่อนไขการแสดงผล ---
                if (distance < 0) {
                    // 🔴 กรณีเลยกำหนด (Overdue) -> แสดงผลแบบนับเดินหน้า
                    mainColor = '#dc2626'; // สีแดงเข้ม
                    subColor = '#fca5a5';  // สีแดงอ่อน
                    prefixText = 'เลยมาแล้ว';
                    icon = '<i class="fas fa-exclamation-circle animate-pulse"></i>';
                } else if (days <= 15) {
                    // 🟠 กรณีใกล้ถึง (Warning) -> แสดงผลแบบนับถอยหลัง
                    mainColor = '#d97706'; // สีส้มเข้ม
                    subColor = '#fcd34d';  // สีส้มอ่อน
                    prefixText = 'เหลืออีก';
                    icon = '⚠️';
                } else {
                    // 🟢 กรณีปกติ (Safe) -> แสดงผลแบบนับถอยหลัง
                    mainColor = '#059669'; // สีเขียวเข้ม
                    subColor = '#6ee7b7';  // สีเขียวอ่อน
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
    // 3. CHART & FILTER LOGIC (ส่วนนี้ทำงานปกติ)
    // ============================================================
    if (chartCanvas) {
        Chart.register(ChartDataLabels);

        // Initialize Select2
        if (equipmentSelect.length) {
            equipmentSelect.select2({
                placeholder: 'ค้นหาอุปกรณ์ทั้งหมด...',
                theme: "classic",
                width: '100%',
                allowClear: true,
                ajax: {
                    url: "/ajax/search-equipment",
                    dataType: 'json',
                    delay: 250,
                    processResults: function (data) {
                        return { results: data.results };
                    },
                    cache: true
                }
            });
        }

        // Functions for Chart Settings (Save/Load/Fetch)
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
                if(savedSettings.year) yearSelect.value = savedSettings.year;
                if(savedSettings.categoryId) categorySelect.value = savedSettings.categoryId;
                if (savedSettings.equipmentId && savedSettings.equipmentText) {
                    const option = new Option(savedSettings.equipmentText, savedSettings.equipmentId, true, true);
                    equipmentSelect.append(option).trigger('change');
                }
                if(savedSettings.selectedSeries) {
                    seriesToggles.forEach(checkbox => { 
                        checkbox.checked = savedSettings.selectedSeries.includes(checkbox.value); 
                    });
                }
            }
        }

        function fetchAndRenderChart() {
            const year = yearSelect.value;
            const categoryId = categorySelect.value;
            const equipmentId = equipmentSelect.val();
            const selectedSeries = Array.from(seriesToggles).filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
            
            const fetchUrl = `/ajax/dashboard-charts?year=${year}&category_id=${categoryId || ''}&equipment_id=${equipmentId || ''}`;

            fetch(fetchUrl)
                .then(response => response.json())
                .then(data => {
                    const datasetsToRender = [];
                    selectedSeries.forEach(seriesKey => {
                        if (data.datasets[seriesKey]) { datasetsToRender.push(data.datasets[seriesKey]); }
                    });

                    if (dashboardChart) { dashboardChart.destroy(); }

                    dashboardChart = new Chart(chartCanvas, {
                        type: 'bar',
                        data: { labels: data.labels, datasets: datasetsToRender },
                        options: {
                            responsive: true, 
                            maintainAspectRatio: false,
                            scales: { 
                                x: { stacked: false }, 
                                y: { stacked: false, beginAtZero: true, ticks: { precision: 0 }, grace: 1 } 
                            },
                            plugins: { 
                                legend: { display: false }, 
                                datalabels: { 
                                    anchor: 'end', 
                                    align: 'top', 
                                    formatter: (value) => (value > 0 ? value : ''), 
                                    font: { weight: 'bold' }, 
                                    color: '#4b5563' 
                                } 
                            }
                        }
                    });
                })
                .catch(error => console.error('Error fetching chart data:', error));
            
            saveSettings();
        }

        // Event Listeners
        yearSelect.addEventListener('change', fetchAndRenderChart);
        categorySelect.addEventListener('change', fetchAndRenderChart);
        equipmentSelect.on('change', fetchAndRenderChart);
        seriesToggles.forEach(toggle => { toggle.addEventListener('change', fetchAndRenderChart); });

        // Init
        loadSettings();
        fetchAndRenderChart();
    }
});