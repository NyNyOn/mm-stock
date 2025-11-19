// public/js/dashboard.js
document.addEventListener('DOMContentLoaded', function () {
    const yearSelect = document.getElementById('chartYearSelect');
    const categorySelect = document.getElementById('chartCategorySelect');
    const equipmentSelect = $('#chartEquipmentSelect'); // Use jQuery for Select2
    const seriesToggles = document.querySelectorAll('.chart-series-checkbox');
    const chartCanvas = document.getElementById('mainDashboardChart');

    if (!chartCanvas) return;

    let dashboardChart = null;
    const SETTINGS_KEY = 'dashboardChartSettings'; // Key สำหรับบันทึกใน Local Storage

    Chart.register(ChartDataLabels);

    // Initialize Equipment Select2
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

    // ✅ NEW: ฟังก์ชันสำหรับบันทึกการตั้งค่า
    function saveSettings() {
        const settings = {
            year: yearSelect.value,
            categoryId: categorySelect.value,
            equipmentId: equipmentSelect.val(),
            equipmentText: equipmentSelect.find('option:selected').text(),
            selectedSeries: Array.from(seriesToggles)
                                 .filter(checkbox => checkbox.checked)
                                 .map(checkbox => checkbox.value)
        };
        localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
    }

    // ✅ NEW: ฟังก์ชันสำหรับโหลดการตั้งค่า
    function loadSettings() {
        const savedSettings = JSON.parse(localStorage.getItem(SETTINGS_KEY));
        if (savedSettings) {
            // ตั้งค่า Dropdowns
            yearSelect.value = savedSettings.year;
            categorySelect.value = savedSettings.categoryId;

            // ตั้งค่า Select2 ของ Equipment (ซับซ้อนกว่าเล็กน้อย)
            if (savedSettings.equipmentId && savedSettings.equipmentText) {
                const option = new Option(savedSettings.equipmentText, savedSettings.equipmentId, true, true);
                equipmentSelect.append(option).trigger('change');
            }

            // ตั้งค่า Checkboxes
            seriesToggles.forEach(checkbox => {
                checkbox.checked = savedSettings.selectedSeries.includes(checkbox.value);
            });
        }
    }

    function fetchAndRenderChart() {
        // ... (ส่วน fetchAndRenderChart เหมือนเดิมทุกประการ) ...
        const year = yearSelect.value;
        const categoryId = categorySelect.value;
        const equipmentId = equipmentSelect.val();
        const selectedSeries = Array.from(seriesToggles)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);

        const fetchUrl = `/ajax/dashboard-charts?year=${year}&category_id=${categoryId || ''}&equipment_id=${equipmentId || ''}`;

        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                const datasetsToRender = [];
                selectedSeries.forEach(seriesKey => {
                    if (data.datasets[seriesKey]) {
                        datasetsToRender.push(data.datasets[seriesKey]);
                    }
                });

                if (dashboardChart) {
                    dashboardChart.destroy();
                }

                dashboardChart = new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: datasetsToRender
                    },
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

        // ✅ NEW: บันทึกการตั้งค่าทุกครั้งที่กราฟถูกวาดใหม่
        saveSettings();
    }

    // Add Event Listeners
    yearSelect.addEventListener('change', fetchAndRenderChart);
    categorySelect.addEventListener('change', fetchAndRenderChart);
    equipmentSelect.on('change', fetchAndRenderChart);
    seriesToggles.forEach(toggle => {
        toggle.addEventListener('change', fetchAndRenderChart);
    });

    // ✅ NEW: เรียกใช้ฟังก์ชันโหลดการตั้งค่าก่อนวาดกราฟครั้งแรก
    loadSettings();
    fetchAndRenderChart();
});
