document.addEventListener('DOMContentLoaded', function () {
    const yearSelect = document.getElementById('chartYearSelect');
    const categorySelect = document.getElementById('chartCategorySelect');
    const equipmentSelect = $('#chartEquipmentSelect'); 
    const seriesToggles = document.querySelectorAll('.chart-series-checkbox');
    const chartCanvas = document.getElementById('mainDashboardChart');
    
    const countdownDisplays = document.querySelectorAll('.stock-countdown-display');
    const SETTINGS_KEY = 'dashboardChartSettings'; 
    let dashboardChart = null;

    // --- POPUP ALERT ---
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
                cancelButtonText: 'รับทราบ'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/stock-checks/create';
                }
            });
        }
    }

    // --- 1. COUNTDOWN TIMER LOGIC (Color + Single Line Fixed) ---
    if (countdownDisplays.length > 0) {
        const updateAllTimers = () => {
            const now = new Date().getTime();

            countdownDisplays.forEach(display => {
                const targetDateStr = display.getAttribute('data-target');
                
                if (!targetDateStr) {
                    display.innerHTML = '<span style="color:red;">No Date</span>';
                    return;
                }

                const targetDate = new Date(targetDateStr).getTime();
                const distance = targetDate - now;

                if (isNaN(targetDate)) {
                    display.innerHTML = '<span style="color:gray; font-size:10px;">Invalid Date</span>';
                    return;
                }

                // คำนวณเวลา
                const absDist = Math.abs(distance);
                const days = Math.floor(absDist / (1000 * 60 * 60 * 24));
                const hours = Math.floor((absDist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((absDist % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((absDist % (1000 * 60)) / 1000);

                // ✅✅✅ กำหนดสีและข้อความตามสถานะ (Color Logic)
                let mainColor, subColor, prefixText, icon;

                if (distance < 0) {
                    // 🔴 เลยกำหนด (Overdue)
                    mainColor = '#dc2626'; // Red-600
                    subColor = '#f87171';  // Red-400
                    prefixText = 'เลยกำหนด';
                    icon = '<i class="fas fa-exclamation-circle"></i>';
                } else if (days <= 15) {
                    // 🟠 ใกล้ถึง (Warning <= 15 days)
                    mainColor = '#d97706'; // Amber-600
                    subColor = '#fbbf24';  // Amber-400
                    prefixText = 'เหลืออีก';
                    icon = '⚠️';
                } else {
                    // 🟢 ปกติ (Safe)
                    mainColor = '#059669'; // Emerald-600
                    subColor = '#34d399';  // Emerald-400
                    prefixText = 'เหลืออีก';
                    icon = '⏳';
                }

                // แสดงผล (Single Line + Colors)
                display.innerHTML = `
                    <div style="display: flex; flex-direction: row; flex-wrap: nowrap; align-items: baseline; justify-content: center; gap: 3px; width: 100%; white-space: nowrap; overflow: hidden; font-size: 11px; color: ${mainColor};">
                        
                        <span style="font-weight: bold; margin-right: 2px;">${icon} ${prefixText}</span>
                        
                        <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${days}</span>
                        <span style="font-size: 9px; color: ${subColor};">วัน.</span>

                        <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${hours.toString().padStart(2, '0')}</span>
                        <span style="font-size: 9px; color: ${subColor};">ชม.</span>

                        <span style="font-family: monospace; font-weight: bold; font-size: 13px;">${minutes.toString().padStart(2, '0')}</span>
                        <span style="font-size: 9px; color: ${subColor};">น.</span>

                        <span style="font-family: monospace; font-weight: bold; font-size: 13px; min-width: 16px; text-align: center;">${seconds.toString().padStart(2, '0')}</span>
                        <span style="font-size: 9px; color: ${subColor};">วิ.</span>
                    </div>
                `;
            });
        };

        updateAllTimers();
        setInterval(updateAllTimers, 1000);
    }

    // --- 2. CHART & FILTER LOGIC ---
    if (chartCanvas) {
        Chart.register(ChartDataLabels);

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
                    seriesToggles.forEach(checkbox => { checkbox.checked = savedSettings.selectedSeries.includes(checkbox.value); });
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
                            responsive: true, maintainAspectRatio: false,
                            scales: { x: { stacked: false }, y: { stacked: false, beginAtZero: true, ticks: { precision: 0 }, grace: 1 } },
                            plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', formatter: (value) => (value > 0 ? value : ''), font: { weight: 'bold' }, color: '#4b5563' } }
                        }
                    });
                })
                .catch(error => console.error('Error fetching chart data:', error));
            saveSettings();
        }

        yearSelect.addEventListener('change', fetchAndRenderChart);
        categorySelect.addEventListener('change', fetchAndRenderChart);
        equipmentSelect.on('change', fetchAndRenderChart);
        seriesToggles.forEach(toggle => { toggle.addEventListener('change', fetchAndRenderChart); });

        loadSettings();
        fetchAndRenderChart();
    }
});