// SmartCampus+ - JavaScript principal
class SmartCampus {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initTooltips();
        this.initCharts();
        this.autoUpdateData();
    }
    
    bindEvents() {
        // Gestion des formulaires AJAX
        document.addEventListener('submit', this.handleAjaxForm.bind(this));
        
        // Gestion des notifications
        this.initNotifications();
        
        // Gestion du calendrier
        this.initCalendar();
        
        // Gestion des modals
        this.initModals();
    }
    
    initTooltips() {
        // Initialisation des tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    initCharts() {
        // Graphiques budgétaires simples avec Canvas
        const budgetCanvas = document.getElementById('budgetChart');
        if (budgetCanvas) {
            this.drawBudgetChart(budgetCanvas);
        }
    }
    
    drawBudgetChart(canvas) {
        const ctx = canvas.getContext('2d');
        const data = this.getBudgetData();
        
        // Graphique en secteurs simple
        let total = data.reduce((sum, item) => sum + item.amount, 0);
        let currentAngle = 0;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 80;
        
        const colors = ['#0d6efd', '#198754', '#fd7e14', '#dc3545', '#ffc107', '#6f42c1'];
        
        data.forEach((item, index) => {
            const sliceAngle = (item.amount / total) * 2 * Math.PI;
            
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.lineTo(centerX, centerY);
            ctx.fillStyle = colors[index % colors.length];
            ctx.fill();
            
            currentAngle += sliceAngle;
        });
    }
    
    getBudgetData() {
        // Données factices pour le graphique
        return [
            { category: 'Logement', amount: 600 },
            { category: 'Transport', amount: 80 },
            { category: 'Restauration', amount: 300 },
            { category: 'Loisirs', amount: 150 },
            { category: 'Études', amount: 100 }
        ];
    }
    
    initNotifications() {
        // Vérification des notifications périodiques
        setInterval(() => {
            this.checkNotifications();
        }, 30000); // Toutes les 30 secondes
    }
    
    async checkNotifications() {
        try {
            const response = await fetch('api/notifications.php');
            const notifications = await response.json();
            
            notifications.forEach(notification => {
                this.showNotification(notification.message, notification.type);
            });
        } catch (error) {
            console.error('Erreur lors de la vérification des notifications:', error);
        }
    }
    
    showNotification(message, type = 'info') {
        const notificationArea = document.getElementById('notification-area') || this.createNotificationArea();
        
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show slide-up`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        notificationArea.appendChild(notification);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    createNotificationArea() {
        const area = document.createElement('div');
        area.id = 'notification-area';
        area.className = 'position-fixed top-0 end-0 p-3';
        area.style.zIndex = '9999';
        document.body.appendChild(area);
        return area;
    }
    
    initCalendar() {
        const calendarContainer = document.getElementById('calendar-container');
        if (calendarContainer) {
            this.renderCalendar(calendarContainer);
        }
    }
    
    renderCalendar(container) {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth();
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        const monthNames = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>${monthNames[month]} ${year}</h5>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="smartCampus.changeMonth(-1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="smartCampus.changeMonth(1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="row g-1">
        `;
        
        const dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        dayNames.forEach(day => {
            html += `<div class="col text-center fw-bold py-2">${day}</div>`;
        });
        
        const current = new Date(startDate);
        for (let week = 0; week < 6; week++) {
            for (let day = 0; day < 7; day++) {
                const isCurrentMonth = current.getMonth() === month;
                const isToday = current.toDateString() === today.toDateString();
                
                html += `
                    <div class="col">
                        <div class="calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${isToday ? 'bg-primary text-white' : ''}" 
                             data-date="${current.toISOString().split('T')[0]}">
                            <div class="fw-bold">${current.getDate()}</div>
                            <div class="calendar-events"></div>
                        </div>
                    </div>
                `;
                current.setDate(current.getDate() + 1);
            }
        }
        
        html += '</div>';
        container.innerHTML = html;
        
        // Charger les événements du calendrier
        this.loadCalendarEvents();
    }
    
    async loadCalendarEvents() {
        try {
            const response = await fetch('api/calendar-events.php');
            const events = await response.json();
            
            events.forEach(event => {
                const dayElement = document.querySelector(`[data-date="${event.date}"] .calendar-events`);
                if (dayElement) {
                    const eventDiv = document.createElement('div');
                    eventDiv.className = 'calendar-event';
                    eventDiv.textContent = event.title;
                    eventDiv.title = event.description;
                    dayElement.appendChild(eventDiv);
                }
            });
        } catch (error) {
            console.error('Erreur lors du chargement des événements:', error);
        }
    }
    
    initModals() {
        // Gestion des modals Bootstrap
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="modal"]')) {
                const targetModal = document.querySelector(e.target.getAttribute('data-bs-target'));
                if (targetModal) {
                    const modal = new bootstrap.Modal(targetModal);
                    modal.show();
                }
            }
        });
    }
    
    async handleAjaxForm(e) {
        if (!e.target.matches('form[data-ajax="true"]')) return;
        
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Affichage du loading
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Chargement...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message || 'Opération réussie', 'success');
                
                // Reset du formulaire si demandé
                if (result.reset) {
                    form.reset();
                }
                
                // Rechargement de la page si demandé
                if (result.reload) {
                    setTimeout(() => location.reload(), 1000);
                }
                
                // Fermeture du modal si présent
                const modal = form.closest('.modal');
                if (modal) {
                    bootstrap.Modal.getInstance(modal).hide();
                }
            } else {
                this.showNotification(result.message || 'Une erreur est survenue', 'danger');
            }
        } catch (error) {
            console.error('Erreur AJAX:', error);
            this.showNotification('Erreur de communication avec le serveur', 'danger');
        } finally {
            // Restauration du bouton
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    autoUpdateData() {
        // Mise à jour automatique des données importantes
        setInterval(() => {
            this.updateDashboardStats();
        }, 60000); // Toutes les minutes
    }
    
    async updateDashboardStats() {
        try {
            const response = await fetch('api/dashboard-stats.php');
            const stats = await response.json();
            
            // Mise à jour des statistiques sur le dashboard
            Object.keys(stats).forEach(key => {
                const element = document.getElementById(`stat-${key}`);
                if (element) {
                    element.textContent = stats[key];
                }
            });
        } catch (error) {
            console.error('Erreur lors de la mise à jour des statistiques:', error);
        }
    }
    
    // Algorithme de matching simple pour les groupes d'étude
    matchStudentsForGroup(students, subject, maxSize = 6) {
        // Tri par affinité (simulation simple basée sur la matière et le niveau)
        const matches = students
            .filter(student => student.subjects.includes(subject))
            .sort((a, b) => {
                // Score de compatibilité simple
                const scoreA = this.calculateCompatibilityScore(a, subject);
                const scoreB = this.calculateCompatibilityScore(b, subject);
                return scoreB - scoreA;
            })
            .slice(0, maxSize);
        
        return matches;
    }
    
    calculateCompatibilityScore(student, subject) {
        let score = 0;
        
        // Bonus pour le niveau académique similaire
        score += student.academicYear * 10;
        
        // Bonus pour les disponibilités communes
        score += student.availability.length * 5;
        
        // Bonus pour l'expérience dans la matière
        if (student.strongSubjects && student.strongSubjects.includes(subject)) {
            score += 20;
        }
        
        return score;
    }
}

// Initialisation de l'application
const smartCampus = new SmartCampus();

// Utilitaires globaux
window.SmartCampusUtils = {
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    },
    
    formatDate: (date) => {
        return new Intl.DateTimeFormat('fr-FR').format(new Date(date));
    },
    
    formatDateTime: (datetime) => {
        return new Intl.DateTimeFormat('fr-FR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(datetime));
    }
};