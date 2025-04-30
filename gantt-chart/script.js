document.addEventListener('DOMContentLoaded', function() {
    // Initialize with default dates (current month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('start-date').valueAsDate = firstDay;
    document.getElementById('end-date').valueAsDate = lastDay;
    
    // Load initial data
    loadGanttData(firstDay, lastDay);
    
    // Event listeners
    document.getElementById('refresh-btn').addEventListener('click', function() {
        const startDate = new Date(document.getElementById('start-date').value);
        const endDate = new Date(document.getElementById('end-date').value);
        loadGanttData(startDate, endDate);
    });
    
    document.getElementById('filter-btn').addEventListener('click', function() {
        const startDate = new Date(document.getElementById('start-date').value);
        const endDate = new Date(document.getElementById('end-date').value);
        loadGanttData(startDate, endDate);
    });
});

function loadGanttData(startDate, endDate) {
    fetch('get_gantt_data.php?' + new URLSearchParams({
        start_date: startDate.toISOString().split('T')[0],
        end_date: endDate.toISOString().split('T')[0]
    }))
    .then(response => response.json())
    .then(data => {
        renderGanttChart(data, startDate, endDate);
    })
    .catch(error => {
        console.error('Error loading Gantt data:', error);
        // Fallback to random data if PHP isn't available
        const randomData = generateRandomData(startDate, endDate);
        renderGanttChart(randomData, startDate, endDate);
    });
}

function renderGanttChart(data, startDate, endDate) {
    const container = document.getElementById('gantt-container');
    container.innerHTML = '';
    
    // Calculate date range
    const dates = getDatesBetween(startDate, endDate);
    
    // Create table
    const table = document.createElement('table');
    table.className = 'gantt-chart';
    
    // Create header row with dates
    const headerRow = document.createElement('tr');
    const emptyHeader = document.createElement('th');
    emptyHeader.textContent = 'Task';
    headerRow.appendChild(emptyHeader);
    
    dates.forEach(date => {
        const th = document.createElement('th');
        th.textContent = formatDateHeader(date);
        th.style.minWidth = '30px';
        headerRow.appendChild(th);
    });
    
    table.appendChild(headerRow);
    
    // Create task rows
    data.tasks.forEach(task => {
        const row = document.createElement('tr');
        row.className = 'task-row';
        
        // Task name cell
        const nameCell = document.createElement('td');
        nameCell.textContent = task.name;
        nameCell.title = `${task.name}\nAssigned to: ${task.assigned_to}\nStatus: ${task.status}`;
        row.appendChild(nameCell);
        
        // Create task bar container for each date
        dates.forEach(date => {
            const dateStr = date.toISOString().split('T')[0];
            const cell = document.createElement('td');
            const barContainer = document.createElement('div');
            barContainer.className = 'task-bar-container';
            
            // Check if this date is within the task's duration
            const taskStart = new Date(task.start_date);
            const taskEnd = new Date(task.end_date);
            
            if (date >= taskStart && date <= taskEnd) {
                const bar = document.createElement('div');
                bar.className = `task-bar ${task.status}`;
                
                // Calculate bar width (for the first and last day)
                let width = 100;
                if (isSameDate(date, taskStart) || isSameDate(date, taskEnd)) {
                    const dayStart = isSameDate(date, taskStart) ? taskStart.getHours() / 24 : 0;
                    const dayEnd = isSameDate(date, taskEnd) ? taskEnd.getHours() / 24 : 1;
                    width = (dayEnd - dayStart) * 100;
                }
                
                bar.style.width = `${width}%`;
                
                if (isSameDate(date, taskStart)) {
                    bar.style.left = '0';
                }
                
                // Tooltip with task info
                bar.addEventListener('mouseover', function(e) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'task-info';
                    tooltip.innerHTML = `
                        <strong>${task.name}</strong><br>
                        <strong>Start:</strong> ${formatDate(task.start_date)}<br>
                        <strong>End:</strong> ${formatDate(task.end_date)}<br>
                        <strong>Status:</strong> ${task.status}<br>
                        <strong>Assigned to:</strong> ${task.assigned_to}
                    `;
                    tooltip.style.left = `${e.pageX + 10}px`;
                    tooltip.style.top = `${e.pageY + 10}px`;
                    document.body.appendChild(tooltip);
                    
                    bar.addEventListener('mouseout', function() {
                        document.body.removeChild(tooltip);
                    }, { once: true });
                });
                
                barContainer.appendChild(bar);
            }
            
            cell.appendChild(barContainer);
            row.appendChild(cell);
        });
        
        table.appendChild(row);
    });
    
    container.appendChild(table);
    
    // Add legend
    const legend = document.createElement('div');
    legend.className = 'status-legend';
    legend.innerHTML = `
        <div class="legend-item">
            <div class="legend-color planned"></div>
            <span>Planned</span>
        </div>
        <div class="legend-item">
            <div class="legend-color ongoing"></div>
            <span>Ongoing</span>
        </div>
        <div class="legend-item">
            <div class="legend-color completed"></div>
            <span>Completed</span>
        </div>
        <div class="legend-item">
            <div class="legend-color delayed"></div>
            <span>Delayed</span>
        </div>
    `;
    container.appendChild(legend);
}

// Helper functions
function getDatesBetween(startDate, endDate) {
    const dates = [];
    let currentDate = new Date(startDate);
    
    while (currentDate <= endDate) {
        dates.push(new Date(currentDate));
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
    return dates;
}

function formatDateHeader(date) {
    return date.getDate();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function isSameDate(date1, date2) {
    return date1.toDateString() === date2.toDateString();
}

// Fallback random data generator
function generateRandomData(startDate, endDate) {
    const taskNames = [
        "Website Design",
        "Frontend Development",
        "Backend Development",
        "Database Setup",
        "User Authentication",
        "API Integration",
        "Testing",
        "Deployment",
        "Content Creation",
        "SEO Optimization"
    ];
    
    const assignees = [
        "John Doe",
        "Jane Smith",
        "Robert Johnson",
        "Emily Davis",
        "Michael Wilson"
    ];
    
    const statuses = ["planned", "ongoing", "completed", "delayed"];
    
    const range = endDate - startDate;
    const dayInMs = 24 * 60 * 60 * 1000;
    
    const tasks = [];
    
    for (let i = 0; i < 8; i++) {
        const taskDuration = Math.floor(Math.random() * 10) + 2; // 2-11 days
        const taskStartOffset = Math.floor(Math.random() * (range/dayInMs - taskDuration));
        
        const taskStart = new Date(startDate.getTime() + taskStartOffset * dayInMs);
        const taskEnd = new Date(taskStart.getTime() + taskDuration * dayInMs);
        
        // Randomize hours for more realistic times
        taskStart.setHours(Math.floor(Math.random() * 24), 0, 0, 0);
        taskEnd.setHours(Math.floor(Math.random() * 24), 0, 0, 0);
        
        tasks.push({
            id: i + 1,
            name: taskNames[Math.floor(Math.random() * taskNames.length)],
            start_date: taskStart.toISOString(),
            end_date: taskEnd.toISOString(),
            status: statuses[Math.floor(Math.random() * statuses.length)],
            assigned_to: assignees[Math.floor(Math.random() * assignees.length)],
            progress: Math.floor(Math.random() * 100)
        });
    }
    
    return { tasks };
}