// public/js/dashboard.js
function dashboardData() {
    return {
        yearlyChart: null,
        weeklyChart: null,
        productionChart: null,
        distributionChart: null, // NEW
        stats: {
            totalNetCane: 0, totalNetAmount: 0, activePlanters: 0, totalPlanters: 0,
            currentCropYear: '', currentWeek: 0, quedanPrice: 0, molassesPrice: 0,
            activeLoans: 0, quedanType: 'N/A', caneChange: 0, amountChange: 0,
            totalDeductions: 0, averageYield: 0, bestWeek: 0, bestWeekCane: 0,
            collectionRate: 0, riskPlanters: 0, pendingApprovals: 0
        },
        yearlyData: { labels: [], datasets: [{ data: [] }] },
        weeklyData: { labels: [], datasets: [{ data: [] }] },
        monthlyData: { labels: [], datasets: [{ data: [] }] },
        distributionData: { labels: [], datasets: [{ data: [], backgroundColor: [] }] }, // NEW
        activities: [],
        topPlanters: [],
        recentPrices: [],
        alerts: [],
        recommendations: [],
        riskPlanters: [],
        loanStats: {},
        loanChart: null,
        selectedCropYear: null,
        selectedWeek: null,
        dataRequestId: 0,

        init() {
            this.loadDashboardData();
            window.addEventListener('filter-changed', (event) => {
                this.loadDashboardData(event.detail.cropYear, event.detail.week);
            });
            window.addEventListener('refresh-dashboard', () => {
                this.loadDashboardData();
            });
        },

        async loadDashboardData(cropYear = this.selectedCropYear, week = this.selectedWeek) {
            this.selectedCropYear = cropYear;
            this.selectedWeek = week;
            const requestId = ++this.dataRequestId;
            try {
                let url = this.$el.dataset.dashboardUrl || '/dashboard/data';
                let params = new URLSearchParams();
                if (cropYear) params.append('crop_year', cropYear);
                if (week) params.append('week_no', week);
                if (params.toString()) url += '?' + params.toString();

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                if (!response.ok) throw new Error('Failed to load');
                const data = await response.json();
                if (requestId !== this.dataRequestId) return;

                this.stats = data.stats;
                this.yearlyData = data.yearlyData;
                this.weeklyData = data.weeklyData;
                this.monthlyData = data.monthlyData || { labels: [], datasets: [{ data: [] }] };
                this.distributionData = data.distributionData || { labels: [], datasets: [{ data: [], backgroundColor: [] }] };
                this.activities = data.activities;
                this.topPlanters = data.topPlanters;
                this.recentPrices = data.recentPrices;
                this.alerts = data.alerts || [];
                this.recommendations = data.recommendations || [];
                this.riskPlanters = data.riskPlanters || [];
                this.loanStats = data.loanStats || {};

                setTimeout(() => { this.createCharts(); }, 300);
            } catch (error) { console.error('Error:', error); }
        },

        get sortedRecentPrices() {
            return [...this.recentPrices].sort((a, b) => {
                const idA = parseInt(String(a.id).replace(/\D/g, ''));
                const idB = parseInt(String(b.id).replace(/\D/g, ''));
                return idB - idA;
            });
        },

        createCharts() {
    const yCanvas = document.getElementById('yearlyChart');
    const wCanvas = document.getElementById('weeklyChart');
    const mCanvas = document.getElementById('monthlyChart');
    const dCanvas = document.getElementById('distributionChart');
    const lCanvas = document.getElementById('loanChart');

    if (this.yearlyChart) { this.yearlyChart.destroy(); this.yearlyChart = null; }
    if (this.weeklyChart) { this.weeklyChart.destroy(); this.weeklyChart = null; }
    if (this.productionChart) { this.productionChart.destroy(); this.productionChart = null; }
    if (this.distributionChart) { this.distributionChart.destroy(); this.distributionChart = null; }
    if (this.loanChart) { this.loanChart.destroy(); this.loanChart = null; }

    // Yearly Chart
    if (yCanvas) {
        const ctx1 = yCanvas.getContext('2d');
        const gradient1 = ctx1.createLinearGradient(0, 0, 0, 320);
        gradient1.addColorStop(0, 'rgba(8, 122, 61, 0.58)');
        gradient1.addColorStop(1, 'rgba(8, 122, 61, 0.14)');
        this.yearlyChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: this.yearlyData.labels,
                datasets: [{
                    label: 'Net Cane (tons)',
                    data: this.yearlyData.datasets[0].data,
                    backgroundColor: gradient1,
                    borderColor: '#087a3d',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } }
            }
        });
    }

    // Weekly Chart
    if (wCanvas) {
        const ctx2 = wCanvas.getContext('2d');
        const gradient2 = ctx2.createLinearGradient(0, 0, 0, 320);
        gradient2.addColorStop(0, 'rgba(8, 122, 61, 0.18)');
        gradient2.addColorStop(1, 'rgba(8, 122, 61, 0)');
        this.weeklyChart = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: this.weeklyData.labels,
                datasets: [{
                    label: 'Net Cane (tons)',
                    data: this.weeklyData.datasets[0].data,
                    borderColor: '#087a3d',
                    backgroundColor: gradient2,
                    borderWidth: 2.5, tension: 0.4, fill: true,
                    pointRadius: 5, pointBackgroundColor: '#fff',
                    pointBorderColor: '#087a3d', pointBorderWidth: 2.5, pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } }
            }
        });
    }

    // Monthly Chart
    if (mCanvas) {
        const ctx3 = mCanvas.getContext('2d');
        const monthlyHasData = this.monthlyData.datasets[0].data.some(v => v > 0);
        this.productionChart = new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: this.monthlyData.labels,
                datasets: [{
                    label: 'Monthly Total (tons)',
                    data: this.monthlyData.datasets[0].data,
                    backgroundColor: monthlyHasData ? 'rgba(183, 132, 31, 0.38)' : 'rgba(229, 231, 235, 0.5)',
                    borderColor: monthlyHasData ? '#b7841f' : '#d1d5db',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } }
            }
        });
    }

    // Distribution Pie Chart
    if (dCanvas) {
        const ctx4 = dCanvas.getContext('2d');
        const distHasData = this.distributionData.labels && this.distributionData.labels[0] !== 'No Data';
        this.distributionChart = new Chart(ctx4, {
            type: 'doughnut',
            data: {
                labels: this.distributionData.labels,
                datasets: [{
                    data: this.distributionData.datasets[0].data,
                    backgroundColor: distHasData ? [
                        '#087a3d', '#d9b54a', '#52a86c', '#b7841f', '#1e6b4b',
                        '#88b3a0', '#e0c776', '#498d62', '#a0c999', '#6d894c'
                    ] : ['#e5e7eb'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverBorderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 12,
                            font: { size: 10 },
                            usePointStyle: true,
                            pointStyleWidth: 8,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                if (total === 0) return 'No data';
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return context.label + ': ' + context.raw.toFixed(1) + ' tons (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Loan Chart
    if (lCanvas) {
        const ctx5 = lCanvas.getContext('2d');
        const loanData = this.loanStats || {};
        const totalPrincipal = parseFloat(loanData.total_principal) || 0;
        const totalBalance = parseFloat(loanData.total_balance) || 0;
        const collected = Math.max(0, totalPrincipal - totalBalance);
        const remainingBalance = totalPrincipal - collected;

        if (totalPrincipal === 0) {
            this.loanChart = new Chart(ctx5, {
                type: 'doughnut',
                data: {
                    labels: ['No active loans'],
                    datasets: [{ data: [1], backgroundColor: ['#e5e7eb'], borderWidth: 0 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });
        } else {
            this.loanChart = new Chart(ctx5, {
                type: 'doughnut',
                data: {
                    labels: ['Remaining Balance', 'Collected'],
                    datasets: [{
                        data: [remainingBalance, collected],
                        backgroundColor: ['rgba(183, 132, 31, 0.68)', 'rgba(8, 122, 61, 0.7)'],
                        borderColor: ['#b7841f', '#087a3d'],
                        borderWidth: 2,
                        hoverBorderWidth: 3,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 11 },
                                usePointStyle: true,
                                pointStyleWidth: 8,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': \u20B1' + Number(context.raw).toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
},

        getAlertClass(type) {
            const classes = {
                'warning': 'bg-amber-50 border-amber-200 text-amber-800',
                'danger': 'bg-red-50 border-red-200 text-red-800',
                'info': 'bg-blue-50 border-blue-200 text-blue-800',
                'success': 'bg-green-50 border-green-200 text-green-800',
            };
            return classes[type] || 'bg-gray-50 border-gray-200 text-gray-800';
        },

        getAlertIcon(type) {
            const icons = {
                'warning': 'fas fa-exclamation-triangle text-amber-500',
                'danger': 'fas fa-times-circle text-red-500',
                'info': 'fas fa-info-circle text-blue-500',
                'success': 'fas fa-check-circle text-green-500',
            };
            return icons[type] || 'fas fa-bell text-gray-500';
        },

        formatNumber(number, decimals = 2) {
            if (number === null || number === undefined) return '0';
            return parseFloat(number).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }
    };
}
