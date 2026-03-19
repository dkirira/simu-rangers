// ========================================
// SIMU RANGERS SECURITY LIMITED - SCRIPT
// ========================================

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (hamburger) {
        hamburger.addEventListener('click', function() {
            navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
            hamburger.classList.toggle('active');
        });
    }

    // Close menu when a link is clicked
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (navMenu.style.display === 'flex') {
                navMenu.style.display = 'none';
                hamburger.classList.remove('active');
            }
        });
    });

    // Modal Functionality
    const modal = document.getElementById('serviceModal');
    const requestServiceBtn = document.getElementById('requestServiceBtn');
    const floatingRequestBtn = document.getElementById('floatingRequestBtn');
    const modalClose = document.querySelector('.modal-close');

    // Open modal on Request Service button click
    if (requestServiceBtn) {
        requestServiceBtn.addEventListener('click', function() {
            modal.classList.add('active');
        });
    }

    // Open modal on Floating Request button click
    if (floatingRequestBtn) {
        floatingRequestBtn.addEventListener('click', function() {
            modal.classList.add('active');
        });
    }

    // Close modal on close button click
    if (modalClose) {
        modalClose.addEventListener('click', function() {
            modal.classList.remove('active');
        });
    }

    // Close modal when clicking outside the modal content
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Form Submission
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const formData = new FormData(this);
            const name = this.querySelector('input[type="text"]').value;
            
            // Show success message
            showNotification('Thank you for your request! We will contact you shortly.', 'success');
            
            // Reset form
            this.reset();
        });
    }

    // Scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all cards and sections
    document.querySelectorAll('.service-card, .stat-card, .feature-item, .contact-info-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                const targetElement = document.querySelector(href);
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add animation to header on scroll
    const navbar = document.querySelector('.navbar');
    let lastScrollTop = 0;

    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            navbar.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.5)';
        } else {
            navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.3)';
        }
        
        lastScrollTop = scrollTop;
    });

    // Parallax effect for hero section
    const hero = document.querySelector('.hero');
    window.addEventListener('scroll', function() {
        let topValue = window.pageYOffset;
        if (hero) {
            hero.style.backgroundPosition = '0 ' + topValue * 0.5 + 'px';
        }
    });
});

// Notification Function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1.5rem;
        background: ${type === 'success' ? '#25d366' : '#ff8c00'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        animation: slideInRight 0.5s ease;
        max-width: 400px;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(function() {
        notification.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(function() {
            notification.remove();
        }, 500);
    }, 5000);
}

// Add keyframe animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }

    .hamburger.active span:nth-child(1) {
        transform: rotate(-45deg) translate(-5px, 6px);
    }

    .hamburger.active span:nth-child(2) {
        opacity: 0;
    }

    .hamburger.active span:nth-child(3) {
        transform: rotate(45deg) translate(-5px, -6px);
    }

    @media (max-width: 768px) {
        .nav-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            flex-direction: column;
            background: linear-gradient(135deg, #0a1f3d 0%, #0f2847 100%);
            border-bottom: 2px solid #d4af37;
            gap: 0;
            padding: 2rem 0;
        }

        .nav-link {
            padding: 1rem 2rem;
            text-align: left;
        }
    }
`;
document.head.appendChild(style);

// Utility function for click-to-call
function initiateCall(phoneNumber) {
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        window.location.href = 'tel:' + phoneNumber;
    }
}

// Counter animation for stats
function animateCounter(element, target, duration = 2000) {
    const increment = target / (duration / 16);
    let current = 0;

    const counter = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target + '+';
            clearInterval(counter);
        } else {
            element.textContent = Math.floor(current) + '+';
        }
    }, 16);
}

// Trigger counter animations when stats section is visible
document.addEventListener('DOMContentLoaded', function() {
    const stats = document.querySelectorAll('.stat-card h3');
    let hasAnimated = false;

    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !hasAnimated) {
                hasAnimated = true;
                stats.forEach(stat => {
                    const target = parseInt(stat.textContent);
                    animateCounter(stat, target);
                });
            }
        });
    }, { threshold: 0.5 });

    const statsSection = document.querySelector('.stats-grid');
    if (statsSection) {
        statsObserver.observe(statsSection);
    }
});

// Smooth scroll behavior for older browsers
if (!CSS.supports('scroll-behavior', 'smooth')) {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
}

// Add loading animation to services/stats
window.addEventListener('load', function() {
    const cards = document.querySelectorAll('.service-card, .stat-card, .feature-item');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Handle form input focus states
document.querySelectorAll('.form-group input, .form-group textarea, .form-group select').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.borderColor = '#d4af37';
    });

    input.addEventListener('blur', function() {
        this.parentElement.style.borderColor = '';
    });
});

// Go to form and close modal
function goToForm() {
    const modal = document.getElementById('serviceModal');
    if (modal) modal.classList.remove('active');

    // Open Gmail compose in a new tab with company email prefilled
    const gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&to=simurangers@gmail.com&su=Service%20Request&body=Hello%2C%0A%0AI%20would%20like%20to%20request%20your%20services.%0A';
    window.open(gmailUrl, '_blank', 'noopener');
}
