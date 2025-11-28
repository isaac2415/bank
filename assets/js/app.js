class BankingKhonde {
    constructor() {
        this.baseUrl = window.location.origin;
        // track any polling intervals so we can clear them when navigating
        this.pollingIds = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });

        // Navigation
        document.addEventListener('click', (e) => {
            // only handle plain left-clicks without modifier keys
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            const link = e.target.closest('a.nav-link');
            if (link) {
                // if link points to a different origin or has target, ignore
                if (link.target && link.target !== '_self') return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('http') && !href.startsWith(window.location.origin)) return;

                e.preventDefault();
                this.navigateTo(href);
            }
        });
    }

    validateGroupForm(form) {
        const name = form.querySelector('#name').value.trim();
        const meetingDays = form.querySelector('#meeting_days').value.trim();
        const contributionAmount = parseFloat(form.querySelector('#contribution_amount').value);
        const interestRate = parseFloat(form.querySelector('#interest_rate').value);
        const loanRepaymentDays = parseInt(form.querySelector('#loan_repayment_days').value);

        if (name.length < 3 || name.length > 100) {
            this.showMessage('Group name must be between 3 and 100 characters', 'error');
            return false;
        }

        const days = meetingDays.split(',').map(day => day.trim());
        if (days.some(day => !day.match(/^[A-Za-z]+$/))) {
            this.showMessage('Meeting days must be valid day names separated by commas', 'error');
            return false;
        }

        if (isNaN(contributionAmount) || contributionAmount <= 0) {
            this.showMessage('Contribution amount must be a positive number', 'error');
            return false;
        }

        if (isNaN(interestRate) || interestRate < 0 || interestRate > 100) {
            this.showMessage('Interest rate must be between 0 and 100', 'error');
            return false;
        }

        if (isNaN(loanRepaymentDays) || loanRepaymentDays < 1 || loanRepaymentDays > 365) {
            this.showMessage('Loan repayment period must be between 1 and 365 days', 'error');
            return false;
        }

        return true;
    }

    async handleFormSubmit(form) {
        const formData = new FormData(form);
        const action = form.getAttribute('action') || form.dataset.action;
        
        // Validate group creation form
        if (form.querySelector('input[name="action"]')?.value === 'create') {
            if (!this.validateGroupForm(form)) {
                return;
            }
        }
        
        try {
            const response = await this.apiCall(action, formData);
            if (response.success) {
                this.showMessage(response.message, 'success');
                if (form.dataset.reset) form.reset();
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1000);
                }
            } else {
                this.showMessage(response.message || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showMessage('An error occurred. Please try again.', 'error');
        }
    }

    async apiCall(endpoint, data = null) {
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {}
        };

        if (data instanceof FormData) {
            options.body = data;
        } else if (data) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        const response = await fetch(`/api/${endpoint}`, options);
        return await response.json();
    }

    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.textContent = message;
        
        const existingMessages = document.querySelector('.messages');
        if (!existingMessages) {
            const messagesContainer = document.createElement('div');
            messagesContainer.className = 'messages';
            document.body.prepend(messagesContainer);
        }
        
        document.querySelector('.messages').appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    navigateTo(url) {
        // Implement single page application navigation
        try {
            const resolved = new URL(url, window.location.href);
            // if same as current location, don't reload
            if (resolved.pathname + resolved.search === window.location.pathname + window.location.search) return;
            history.pushState(null, '', resolved.href);
            this.loadPage(resolved.href);
        } catch (err) {
            // fallback: if URL invalid, do full navigation
            window.location.href = url;
        }
    }

    async loadPage(url) {
        try {
            const response = await fetch(url);
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update main content
            const mainContent = doc.querySelector('main');
            // clear any existing polling timers before replacing content
            Object.values(this.pollingIds).forEach(id => clearInterval(id));
            this.pollingIds = {};
            document.querySelector('main').innerHTML = mainContent ? mainContent.innerHTML : '';
            
            // Update page title
            document.title = doc.title;
            
            this.loadInitialData();
        } catch (error) {
            console.error('Error loading page:', error);
        }
    }

    loadInitialData() {
        // Load initial data based on current page
        const path = window.location.pathname;
        if (path.includes('dashboard')) {
            this.loadDashboardData();
        } else if (path.includes('loans')) {
            this.loadLoansData();
        } else if (path.includes('chat')) {
            this.loadChatMessages();
        }
    }

    async loadDashboardData() {
        try {
            const data = await this.apiCall('reports.php?action=dashboard');
            this.updateDashboard(data);
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    updateDashboard(data) {
        // Update dashboard widgets with real data
        if (data.totalMembers) {
            document.getElementById('total-members').textContent = data.totalMembers;
        }
        if (data.totalLoans) {
            document.getElementById('total-loans').textContent = data.totalLoans;
        }
        if (data.totalBalance) {
            document.getElementById('total-balance').textContent = this.formatCurrency(data.totalBalance);
        }
    }

    formatCurrency(amount) {
        return 'K ' + parseFloat(amount).toFixed(2);
    }

    // Chart functions
    renderMemberContributionChart(data) {
        const ctx = document.getElementById('contribution-chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Contributions',
                    data: data.values,
                    backgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Real-time chat
    startChatPolling(groupId) {
        // clear existing for this group if present
        const key = `chat_${groupId}`;
        if (this.pollingIds[key]) {
            clearInterval(this.pollingIds[key]);
        }

        this.pollingIds[key] = setInterval(async () => {
            try {
                const messages = await this.apiCall(`chat.php?action=get_messages&group_id=${groupId}`);
                this.updateChatMessages(messages);
            } catch (err) {
                console.error('Chat polling error:', err);
            }
        }, 2000);
    }

    updateChatMessages(messages) {
        const container = document.getElementById('chat-messages');
        container.innerHTML = messages.map(msg => `
            <div class="chat-message ${msg.is_own ? 'own' : ''}">
                <strong>${msg.user_name}:</strong> ${msg.message}
                <small>${new Date(msg.created_at).toLocaleTimeString()}</small>
            </div>
        `).join('');
        container.scrollTop = container.scrollHeight;
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.bankingApp = new BankingKhonde();
});