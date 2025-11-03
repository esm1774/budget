// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('ar-SA', {
        style: 'currency',
        currency: 'SAR'
    }).format(amount);
}

// Animate numbers on scroll
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start).toLocaleString('ar-SA');
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Intersection Observer for stats animation
document.addEventListener('DOMContentLoaded', function() {
    const stats = document.querySelectorAll('.stat-value');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const value = parseFloat(entry.target.textContent.replace(/[^\d.]/g, ''));
                if (!isNaN(value)) {
                    animateValue(entry.target, 0, value, 1000);
                }
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    stats.forEach(stat => observer.observe(stat));
});

// Export table to PDF (simple version)
function exportToPDF() {
    window.print();
}

// Filter distributions by date range
function filterDistributions(startDate, endDate) {
    const rows = document.querySelectorAll('.distribution-row');
    rows.forEach(row => {
        const rowDate = row.dataset.date;
        if (rowDate >= startDate && rowDate <= endDate) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}