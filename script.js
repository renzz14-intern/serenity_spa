// Function to switch between login and register forms
function showForm(formId) {
    console.log('showForm called with:', formId); // Debug log
    
    // Hide all forms
    const forms = document.querySelectorAll('.form-box');
    forms.forEach(form => {
        form.classList.remove('active');
    });
    
    // Show the selected form
    const targetForm = document.getElementById(formId);
    if (targetForm) {
        targetForm.classList.add('active');
        console.log('Form shown:', formId); // Debug log
    } else {
        console.error('Form not found:', formId); // Debug log
    }
    
    // Update the URL hash for better UX (optional)
    if (formId === 'register-form') {
        window.location.hash = '#register';
    } else {
        window.location.hash = '#login';
    }
    
    // Prevent default behavior
    return false;
}

// Check URL hash on page load to show appropriate form
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded'); // Debug log
    
    // Check if either form already has the 'active' class from PHP
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    if (loginForm && registerForm) {
        const loginIsActive = loginForm.classList.contains('active');
        const registerIsActive = registerForm.classList.contains('active');
        
        console.log('Login form active:', loginIsActive);
        console.log('Register form active:', registerIsActive);
        
        // If PHP already set a form as active, don't override it
        if (loginIsActive || registerIsActive) {
            console.log('PHP set active form, not overriding');
            // Just update the hash to match the active form
            if (registerIsActive) {
                window.location.hash = '#register';
            } else {
                window.location.hash = '#login';
            }
            return; // Exit early, don't call showForm()
        }
        
        // Only if NO form is active from PHP, then use hash or default
        const hash = window.location.hash;
        console.log('Current hash:', hash);
        
        if (hash === '#register') {
            showForm('register-form');
        } else {
            showForm('login-form');
        }
    }
    
    // Initialize main website functionality if we're on the main site
    initializeMainWebsite();
});

// Handle browser back/forward buttons
window.addEventListener('hashchange', function() {
    const hash = window.location.hash;
    if (hash === '#register') {
        showForm('register-form');
    } else {
        showForm('login-form');
    }
});

// Add event listeners to form switch links to prevent default behavior
document.addEventListener('DOMContentLoaded', function() {
    // Find all form switch links and add proper event listeners
    const formSwitchLinks = document.querySelectorAll('.form-switch a');
    formSwitchLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default link behavior
            e.stopPropagation(); // Stop event bubbling
            
            // Determine which form to show based on link text or data attribute
            const linkText = this.textContent.toLowerCase();
            if (linkText.includes('create') || linkText.includes('register')) {
                showForm('register-form');
            } else if (linkText.includes('sign') || linkText.includes('login')) {
                showForm('login-form');
            }
            
            return false;
        });
    });
});

// Add smooth transitions and animations
document.addEventListener('DOMContentLoaded', function() {
    // Add click animations to buttons
    const buttons = document.querySelectorAll('button, .action-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            // Add ripple animation CSS if not exists
            if (!document.getElementById('ripple-style')) {
                const style = document.createElement('style');
                style.id = 'ripple-style';
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(2);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Auto-hide messages after 10 seconds
    const messages = document.querySelectorAll('.success-message, .error-message, .logout-message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            setTimeout(() => message.remove(), 500);
        }, 10000);
    });
});

// ==================================================
// MAIN WEBSITE FUNCTIONALITY (from index.html)
// ==================================================

// Check user authentication status
let isLoggedIn = false; // Boolean tracking authentication status
let currentUser = null; // Object storing user data

// Function to initialize main website functionality
function initializeMainWebsite() {
    // Only run if we're on the main website (check for specific elements)
    if (!document.getElementById('auth-section')) {
        return; // Not on main website
    }
    
    checkAuthStatus(); // Checks URL parameters for login/logout status
    initializePeekSlider();
    initializeDropdown();
    initializeSmoothScrolling();
    initializeCarousel();
    initializeScrollNavigation();
}

// Function to check login status when page loads
function checkAuthStatus() {
    // Check if there's a login success message in URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const loginSuccess = urlParams.get('login_success');
    const logoutSuccess = urlParams.get('logout_success');
    const userName = urlParams.get('user_name');
    
    if (loginSuccess === '1' && userName) {
        // User just logged in successfully
        currentUser = {
            name: decodeURIComponent(userName),
            isLoggedIn: true
        };
        
        // Store in localStorage for persistence
        localStorage.setItem('userAuth', JSON.stringify(currentUser));
        
        // Clean the URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        // Show welcome message
        showWelcomeMessage(`Welcome, ${currentUser.name}! You are now logged in.`);
        
        updateAuthDisplay(); // Shows/hides login button vs user menu
    } else if (logoutSuccess === '1' && userName) {
        // User just logged out
        const loggedOutUserName = decodeURIComponent(userName);
        
        // Clear any stored auth data
        localStorage.removeItem('userAuth');
        currentUser = null;
        isLoggedIn = false;
        
        // Clean the URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        // Show logout message
        showWelcomeMessage(`Goodbye, ${loggedOutUserName}! You have been logged out successfully.`);
        
        updateAuthDisplay();
    } else {
        // Check if user is already logged in from localStorage
        const storedAuth = localStorage.getItem('userAuth');
        if (storedAuth) {
            try {
                currentUser = JSON.parse(storedAuth);
                if (currentUser.isLoggedIn) {
                    updateAuthDisplay();
                }
            } catch (e) {
                localStorage.removeItem('userAuth');
            }
        }
    }
}

// Function to update the authentication display
function updateAuthDisplay() {
    const loginButton = document.getElementById('login-button');
    const userSection = document.getElementById('user-section');
    const userNameSpan = document.getElementById('user-name');
    
    if (currentUser && currentUser.isLoggedIn) {
        if (loginButton) loginButton.style.display = 'none';
        if (userSection) userSection.style.display = 'block';
        if (userNameSpan) userNameSpan.textContent = currentUser.name;
        isLoggedIn = true;
    } else {
        if (loginButton) loginButton.style.display = 'inline-block';
        if (userSection) userSection.style.display = 'none';
        isLoggedIn = false;
    }
}

// FIXED: Function to handle service booking with authentication check
function handleServiceBooking(serviceName) {
    // Check if user is logged in
    const storedAuth = localStorage.getItem('userAuth');
    let userIsAuthenticated = false;
    
    if (storedAuth) {
        try {
            const authData = JSON.parse(storedAuth);
            userIsAuthenticated = authData && authData.isLoggedIn;
        } catch (e) {
            localStorage.removeItem('userAuth');
        }
    }
    
    // If not logged in, redirect to login page
    if (!userIsAuthenticated) {
        // Store the intended booking action for after login
        localStorage.setItem('pendingBooking', 'true');
        if (serviceName) {
            localStorage.setItem('selectedService', serviceName);
        }
        
        // Show a message to inform user they need to login
        showWelcomeMessage('Please login or create an account to book a service.');
        
        // Redirect to login/register page
        window.location.href = 'index.php';
        return;
    }
    
    // User is logged in, proceed to booking page
    try {
        // Store selected service in localStorage so booking page can pre-select it
        if (serviceName) {
            localStorage.setItem('selectedService', serviceName);
        }
        // Navigate to booking.php
        window.location.href = 'booking.php';
    } catch (err) {
        console.error('Error navigating to booking page', err);
        showWelcomeMessage('Error accessing booking page. Please try again.');
    }
}

// Function to check for pending booking after login
function checkPendingBooking() {
    const pendingBooking = localStorage.getItem('pendingBooking');
    
    if (pendingBooking === 'true' && currentUser && currentUser.isLoggedIn) {
        // Remove the pending booking flag
        localStorage.removeItem('pendingBooking');
        
        // Automatically redirect to booking page after a short delay
        setTimeout(() => {
            window.location.href = 'booking.php';
        }, 2000); // 2 second delay to show welcome message
    }
}

// Function to show welcome message
function showWelcomeMessage(message) {
    const welcomeDiv = document.getElementById('welcome-message');
    const welcomeText = document.getElementById('welcome-text');
    
    if (welcomeDiv && welcomeText) {
        welcomeText.textContent = message;
        welcomeDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            closeWelcomeMessage();
        }, 5000);
    }
}

// Function to close welcome message
function closeWelcomeMessage() {
    const welcomeDiv = document.getElementById('welcome-message');
    if (welcomeDiv) {
        welcomeDiv.style.display = 'none';
    }
}

// Initialize dropdown functionality
function initializeDropdown() {
    const userButton = document.getElementById('user-button');
    const dropdownContent = document.getElementById('dropdown-content');
    
    if (userButton && dropdownContent) {
        userButton.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownContent.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userButton.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });
    }
}

// Handle logout
function handleLogout() {
    localStorage.removeItem('userAuth');
    localStorage.removeItem('pendingBooking'); // Clear any pending booking
    localStorage.removeItem('selectedService'); // Clear any selected service
    currentUser = null;
    isLoggedIn = false;
    updateAuthDisplay();
}

// Listen for logout events from other pages
window.addEventListener('storage', function(e) {
    if (e.key === 'userAuth' && !e.newValue) {
        handleLogout();
    }
});

// Initialize smooth scrolling for navigation links
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Initialize peek slider functionality - FIXED VERSION
function initializePeekSlider() {
    const peekSlider = document.querySelector('.peek-slider');
    const peekPrev = document.querySelector('.peek-btn.prev');
    const peekNext = document.querySelector('.peek-btn.next');
    
    if (!peekSlider || !peekPrev || !peekNext) {
        console.log('Peek slider elements not found');
        return;
    }
    
    // Calculate scroll distance based on slide width + gap
    const slideWidth = 300; // Fixed slide width
    const gap = 20; // Gap between slides
    const scrollDistance = slideWidth + gap;
    
    // Clear any existing event listeners by removing and re-adding
    const newPeekNext = peekNext.cloneNode(true);
    const newPeekPrev = peekPrev.cloneNode(true);
    
    peekNext.parentNode.replaceChild(newPeekNext, peekNext);
    peekPrev.parentNode.replaceChild(newPeekPrev, peekPrev);
    
    // Add event listeners with proper event handling
    newPeekNext.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('Next button clicked');
        
        // Scroll to next slide
        peekSlider.scrollBy({
            left: scrollDistance,
            behavior: 'smooth'
        });
        
        // Prevent any layout recalculation issues
        setTimeout(() => {
            newPeekNext.blur();
        }, 100);
        
        return false;
    });
    
    newPeekPrev.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('Prev button clicked');
        
        // Scroll to previous slide
        peekSlider.scrollBy({
            left: -scrollDistance,
            behavior: 'smooth'
        });
        
        // Prevent any layout recalculation issues
        setTimeout(() => {
            newPeekPrev.blur();
        }, 100);
        
        return false;
    });
    
    // Optional: Update navigation buttons based on scroll position
    const updateNavigationButtons = () => {
        const isAtStart = peekSlider.scrollLeft <= 10;
        const isAtEnd = peekSlider.scrollLeft >= (peekSlider.scrollWidth - peekSlider.clientWidth - 10);
        
        newPeekPrev.style.opacity = isAtStart ? '0.5' : '1';
        newPeekNext.style.opacity = isAtEnd ? '0.5' : '1';
        
        // Ensure buttons stay in correct position
        newPeekPrev.style.position = 'absolute';
        newPeekPrev.style.left = '10px';
        newPeekPrev.style.top = '50%';
        newPeekPrev.style.transform = 'translateY(-50%)';
        
        newPeekNext.style.position = 'absolute';
        newPeekNext.style.right = '10px';
        newPeekNext.style.top = '50%';
        newPeekNext.style.transform = 'translateY(-50%)';
    };

    // Add scroll listener with throttling to prevent excessive updates
    let scrollTimeout;
    peekSlider.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(updateNavigationButtons, 50);
    });
    
    // Initial setup
    updateNavigationButtons();
    
    // Debug information
    console.log('Peek slider initialized:', {
        scrollWidth: peekSlider.scrollWidth,
        clientWidth: peekSlider.clientWidth,
        slideCount: document.querySelectorAll('.peek-slide').length,
        scrollDistance: scrollDistance
    });
    
    // Ensure proper positioning on window resize
    window.addEventListener('resize', () => {
        setTimeout(updateNavigationButtons, 100);
    });
}

// Initialize carousel functionality
function initializeCarousel() {
    let slideIndex = 1;
    const totalSlides = 6;
    
    function showSlides(n) {
        const slides = document.querySelector('.carousel-wrapper');
        const dots = document.querySelectorAll('.dot');
        
        if (n > totalSlides) { slideIndex = 1; }
        if (n < 1) { slideIndex = totalSlides; }
        
        const translateX = -(slideIndex - 1) * 100; // Show 1 slide at a time
        if (slides) {
            slides.style.transform = `translateX(${translateX}%)`;
        }
        
        dots.forEach(dot => dot.classList.remove('active'));
        if (dots[slideIndex - 1]) {
            dots[slideIndex - 1].classList.add('active');
        }
    }
    
    function moveSlide(n) {
        slideIndex += n;
        showSlides(slideIndex);
    }
    
    function currentSlide(n) {
        slideIndex = n;
        showSlides(slideIndex);
    }
    
    // Auto-play carousel
    setInterval(() => {
        slideIndex++;
        showSlides(slideIndex);
    }, 5000);
    
    // Initialize carousel
    showSlides(slideIndex);
    
    // Make functions available globally for HTML onclick attributes if needed
    window.moveSlide = moveSlide;
    window.currentSlide = currentSlide;
}

// Initialize scroll-based navigation highlighting
function initializeScrollNavigation() {
    window.addEventListener('scroll', () => {
        let current = '';
        const sections = document.querySelectorAll('section');
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (scrollY >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });

        document.querySelectorAll('.nav-links a').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').includes(current)) {
                link.classList.add('active');
            }
        });
    });
}

// UPDATED: Modified initialization to check for pending booking after login
function initializeMainWebsite() {
    // Only run if we're on the main website (check for specific elements)
    if (!document.getElementById('auth-section')) {
        return; // Not on main website
    }
    
    checkAuthStatus(); // Checks URL parameters for login/logout status
    
    // Check for pending booking after auth status is established
    setTimeout(() => {
        checkPendingBooking();
    }, 500); // Small delay to ensure auth status is set
    
    initializePeekSlider();
    initializeDropdown();
    initializeSmoothScrolling();
    initializeCarousel();
    initializeScrollNavigation();
}

// Make functions available globally for HTML onclick attributes
window.handleServiceBooking = handleServiceBooking;
window.closeWelcomeMessage = closeWelcomeMessage;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize booking system if we're on the booking page
    if (document.querySelector('.booking-container')) {
        console.log('Initializing booking system...');
        window.spaBooking = new SpaBookingSystem();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        
        const activeStep = document.querySelector('.form-step.active');
        if (activeStep) {
            const nextButton = activeStep.querySelector('.next-step');
            const confirmButton = activeStep.querySelector('#confirm-booking-btn');
            
            if (confirmButton) {
                confirmButton.click();
            } else if (nextButton) {
                nextButton.click();
            }
        }
    }
});