document.addEventListener('DOMContentLoaded', () => {
    const dashboardData = window.dashboardData || {};

    const buildDatasetMap = (rows, keyField, valueField) => {
        return Array.isArray(rows)
            ? rows.reduce((accumulator, row) => {
                accumulator[row[keyField]] = Number(row[valueField]) || 0;
                return accumulator;
            }, {})
            : {};
    };

    const priorityMap = buildDatasetMap(dashboardData.prioritySummary, 'priority', 'total');
    const statusMap = buildDatasetMap(dashboardData.statusSummary, 'status', 'total');

    const renderChart = (canvasId, config) => {
        const canvas = document.getElementById(canvasId);

        if (!canvas) {
            return;
        }

        new Chart(canvas, config);
    };

    renderChart('priorityChart', {
        type: 'doughnut',
        data: {
            labels: ['High', 'Medium', 'Low'],
            datasets: [{
                data: [
                    priorityMap.high || 0,
                    priorityMap.medium || 0,
                    priorityMap.low || 0
                ],
                backgroundColor: ['#d62828', '#f4a261', '#2a9d8f'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    renderChart('statusChart', {
        type: 'bar',
        data: {
            labels: ['Pending', 'Assigned', 'Completed'],
            datasets: [{
                label: 'Calls',
                data: [
                    statusMap.pending || 0,
                    statusMap.assigned || 0,
                    statusMap.completed || 0
                ],
                backgroundColor: ['#ffb703', '#219ebc', '#588157'],
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    renderChart('dailyChart', {
        type: 'line',
        data: {
            labels: (dashboardData.dailySummary || []).map((row) => row.label),
            datasets: [{
                label: 'Calls per day',
                data: (dashboardData.dailySummary || []).map((row) => Number(row.total) || 0),
                borderColor: '#006d77',
                backgroundColor: 'rgba(0, 109, 119, 0.18)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    renderChart('weeklyChart', {
        type: 'line',
        data: {
            labels: (dashboardData.weeklySummary || []).map((row) => row.label),
            datasets: [{
                label: 'Calls per week',
                data: (dashboardData.weeklySummary || []).map((row) => Number(row.total) || 0),
                borderColor: '#9c6644',
                backgroundColor: 'rgba(156, 102, 68, 0.18)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    const statusClassMap = {
        pending: 'status-pending',
        assigned: 'status-assigned',
        completed: 'status-completed'
    };

    document.querySelectorAll('.update-status-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const callId = button.dataset.callId;

            button.disabled = true;
            button.textContent = 'Updating...';

            try {
                const response = await fetch('ajax_update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({ call_id: callId }).toString()
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to update call status.');
                }

                const statusLabel = document.getElementById(`status-label-${callId}`);
                if (statusLabel) {
                    statusLabel.textContent = result.status.charAt(0).toUpperCase() + result.status.slice(1);
                    statusLabel.className = `badge rounded-pill status-badge ${statusClassMap[result.status] || ''}`;
                }

                button.dataset.currentStatus = result.status;

                if (result.status === 'completed') {
                    button.textContent = 'Completed';
                    button.disabled = true;
                    return;
                }

                button.textContent = 'Advance Status';
                button.disabled = false;
            } catch (error) {
                alert(error.message);
                button.textContent = 'Advance Status';
                button.disabled = false;
            }
        });
    });
});
