class TaskTracker {
    constructor() {
        this.tasks = [];
        this.currentFilter = 'all';
        this.currentSort = 'created';
        this.taskToDelete = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadTasks();
        this.setupKeyboardShortcuts();
    }
    
    bindEvents() {
        // Add task form
        document.getElementById('addTaskForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleAddTask();
        });
        
        // Edit task form
        document.getElementById('editTaskForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleEditTask();
        });
        
        // Filter tabs
        document.querySelectorAll('#filterTabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleFilterChange(e.target.dataset.filter);
            });
        });
        
        // Sort dropdown
        document.querySelectorAll('[data-sort]').forEach(sortBtn => {
            sortBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleSortChange(e.target.dataset.sort);
            });
        });
        
        // Delete confirmation
        document.getElementById('confirmDeleteTask').addEventListener('click', () => {
            this.handleDeleteTask();
        });
        
        // Modal events
        document.getElementById('addTaskModal').addEventListener('hidden.bs.modal', () => {
            this.resetAddForm();
        });
        
        document.getElementById('editTaskModal').addEventListener('hidden.bs.modal', () => {
            this.resetEditForm();
        });
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N to add new task
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
                modal.show();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                });
            }
        });
        
        // Show keyboard hint
        this.showKeyboardHint();
    }
    
    showKeyboardHint() {
        const hint = document.createElement('div');
        hint.className = 'keyboard-hint';
        hint.innerHTML = 'Press Ctrl+N to add task';
        document.body.appendChild(hint);
        
        setTimeout(() => {
            hint.classList.add('show');
            setTimeout(() => {
                hint.classList.remove('show');
                setTimeout(() => hint.remove(), 300);
            }, 3000);
        }, 1000);
    }
    
    async loadTasks() {
        try {
            this.showLoadingState();
            
            const response = await fetch('api/tasks.php');
            const data = await response.json();
            
            if (data.success) {
                this.tasks = data.tasks;
                this.renderTasks();
                this.updateTaskCount();
            } else {
                this.showError('Failed to load tasks: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: Unable to load tasks');
            console.error('Load tasks error:', error);
        } finally {
            this.hideLoadingState();
        }
    }
    
    async handleAddTask() {
        const form = document.getElementById('addTaskForm');
        const formData = new FormData(form);
        
        const taskData = {
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            dueDate: formData.get('dueDate') || null
        };
        
        if (!taskData.title) {
            this.showError('Task title is required');
            return;
        }
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(taskData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.tasks.unshift(data.task);
                this.renderTasks();
                this.updateTaskCount();
                this.showSuccess('Task added successfully');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                modal.hide();
            } else {
                this.showError('Failed to add task: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: Unable to add task');
            console.error('Add task error:', error);
        }
    }
    
    async handleEditTask() {
        const form = document.getElementById('editTaskForm');
        const formData = new FormData(form);
        
        const taskData = {
            id: formData.get('id'),
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            dueDate: formData.get('dueDate') || null
        };
        
        if (!taskData.title) {
            this.showError('Task title is required');
            return;
        }
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(taskData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                const taskIndex = this.tasks.findIndex(t => t.id === taskData.id);
                if (taskIndex !== -1) {
                    this.tasks[taskIndex] = data.task;
                    this.renderTasks();
                    this.showSuccess('Task updated successfully');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editTaskModal'));
                    modal.hide();
                }
            } else {
                this.showError('Failed to update task: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: Unable to update task');
            console.error('Edit task error:', error);
        }
    }
    
    async handleDeleteTask() {
        if (!this.taskToDelete) return;
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: this.taskToDelete })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.tasks = this.tasks.filter(t => t.id !== this.taskToDelete);
                this.renderTasks();
                this.updateTaskCount();
                this.showSuccess('Task deleted successfully');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteTaskModal'));
                modal.hide();
            } else {
                this.showError('Failed to delete task: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: Unable to delete task');
            console.error('Delete task error:', error);
        } finally {
            this.taskToDelete = null;
        }
    }
    
    async toggleTaskCompletion(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;
        
        try {
            const response = await fetch('api/tasks.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: taskId,
                    completed: !task.completed
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                task.completed = !task.completed;
                this.renderTasks();
                this.showSuccess(task.completed ? 'Task completed!' : 'Task marked as active');
            } else {
                this.showError('Failed to update task: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: Unable to update task');
            console.error('Toggle completion error:', error);
        }
    }
    
    handleFilterChange(filter) {
        this.currentFilter = filter;
        
        // Update active tab
        document.querySelectorAll('#filterTabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
        
        this.renderTasks();
    }
    
    handleSortChange(sort) {
        this.currentSort = sort;
        this.renderTasks();
    }
    
    renderTasks() {
        const container = document.getElementById('tasksList');
        const emptyState = document.getElementById('emptyState');
        
        let filteredTasks = this.getFilteredTasks();
        filteredTasks = this.getSortedTasks(filteredTasks);
        
        if (filteredTasks.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('d-none');
            return;
        }
        
        emptyState.classList.add('d-none');
        
        container.innerHTML = filteredTasks.map(task => this.renderTaskCard(task)).join('');
        
        // Bind events for task cards
        this.bindTaskEvents();
        
        // Add fade-in animation
        container.querySelectorAll('.task-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 50}ms`;
            card.classList.add('fade-in');
        });
    }
    
    renderTaskCard(task) {
        const dueDate = task.dueDate ? new Date(task.dueDate) : null;
        const today = new Date();
        const isOverdue = dueDate && dueDate < today && !task.completed;
        const isDueToday = dueDate && dueDate.toDateString() === today.toDateString();
        
        let dueDateClass = '';
        let dueDateText = '';
        
        if (dueDate) {
            dueDateText = this.formatDate(dueDate);
            if (isOverdue) {
                dueDateClass = 'overdue';
                dueDateText = `<i class="fas fa-exclamation-triangle me-1"></i>Overdue: ${dueDateText}`;
            } else if (isDueToday) {
                dueDateClass = 'due-today';
                dueDateText = `<i class="fas fa-calendar-day me-1"></i>Due today: ${dueDateText}`;
            } else {
                dueDateText = `<i class="fas fa-calendar me-1"></i>Due: ${dueDateText}`;
            }
        }
        
        return `
            <div class="task-card ${task.completed ? 'completed' : ''}" data-task-id="${task.id}">
                <div class="task-header">
                    <input type="checkbox" class="task-checkbox" ${task.completed ? 'checked' : ''} 
                           onchange="taskTracker.toggleTaskCompletion('${task.id}')">
                    <div class="task-content">
                        <h6 class="task-title">${this.escapeHtml(task.title)}</h6>
                        ${task.description ? `<p class="task-description">${this.escapeHtml(task.description)}</p>` : ''}
                        <div class="task-meta">
                            <div class="task-due-date ${dueDateClass}">
                                ${dueDateText || '<i class="fas fa-clock me-1"></i>No due date'}
                            </div>
                            <div class="task-actions">
                                <button class="btn btn-outline-primary btn-sm" onclick="taskTracker.openEditModal('${task.id}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="taskTracker.openDeleteModal('${task.id}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    bindTaskEvents() {
        // Task card hover effects
        document.querySelectorAll('.task-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    }
    
    getFilteredTasks() {
        switch (this.currentFilter) {
            case 'active':
                return this.tasks.filter(task => !task.completed);
            case 'completed':
                return this.tasks.filter(task => task.completed);
            default:
                return this.tasks;
        }
    }
    
    getSortedTasks(tasks) {
        return [...tasks].sort((a, b) => {
            switch (this.currentSort) {
                case 'dueDate':
                    if (!a.dueDate && !b.dueDate) return 0;
                    if (!a.dueDate) return 1;
                    if (!b.dueDate) return -1;
                    return new Date(a.dueDate) - new Date(b.dueDate);
                case 'title':
                    return a.title.localeCompare(b.title);
                case 'created':
                default:
                    return new Date(b.created) - new Date(a.created);
            }
        });
    }
    
    openEditModal(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;
        
        document.getElementById('editTaskId').value = task.id;
        document.getElementById('editTaskTitle').value = task.title;
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskDueDate').value = task.dueDate || '';
        
        const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
        modal.show();
    }
    
    openDeleteModal(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;
        
        this.taskToDelete = taskId;
        document.getElementById('deleteTaskTitle').textContent = task.title;
        
        const modal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
        modal.show();
    }
    
    updateTaskCount() {
        const totalTasks = this.tasks.length;
        const activeTasks = this.tasks.filter(t => !t.completed).length;
        
        document.getElementById('taskCount').textContent = 
            `${activeTasks} active${activeTasks !== 1 ? '' : ''} / ${totalTasks} total`;
    }
    
    showLoadingState() {
        document.getElementById('loadingState').style.display = 'block';
        document.getElementById('tasksList').innerHTML = '';
        document.getElementById('emptyState').classList.add('d-none');
    }
    
    hideLoadingState() {
        document.getElementById('loadingState').style.display = 'none';
    }
    
    showSuccess(message) {
        const toast = document.getElementById('successToast');
        toast.querySelector('.toast-body').textContent = message;
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }
    
    showError(message) {
        const toast = document.getElementById('errorToast');
        toast.querySelector('.toast-body').textContent = message;
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }
    
    resetAddForm() {
        document.getElementById('addTaskForm').reset();
    }
    
    resetEditForm() {
        document.getElementById('editTaskForm').reset();
    }
    
    formatDate(date) {
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        };
        return date.toLocaleDateString(undefined, options);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the application
const taskTracker = new TaskTracker();

// Service Worker Registration (if needed in future)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Service worker can be added here for offline functionality
    });
}
