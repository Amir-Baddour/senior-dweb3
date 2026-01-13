// dashboard-init.js
// Add this at the TOP of your dashboard.html before other scripts

(function initializeDashboard() {
    console.log('[Dashboard] Initializing...');
    
    // Check URL parameters first
    const urlParams = new URLSearchParams(window.location.search);
    const tokenFromUrl = urlParams.get('token');
    const userIdFromUrl = urlParams.get('userId');
    const emailFromUrl = urlParams.get('userEmail');
    const roleFromUrl = urlParams.get('userRole');
    
    // If token is in URL, save it to localStorage and clean URL
    if (tokenFromUrl) {
        console.log('[Dashboard] Token found in URL, saving to localStorage');
        
        try {
            localStorage.setItem('jwt', tokenFromUrl);
            if (userIdFromUrl) localStorage.setItem('userId', userIdFromUrl);
            if (emailFromUrl) localStorage.setItem('userEmail', decodeURIComponent(emailFromUrl));
            if (roleFromUrl) localStorage.setItem('userRole', roleFromUrl);
            
            // Clean URL (remove query parameters)
            window.history.replaceState({}, document.title, window.location.pathname);
            
            console.log('[Dashboard] Token saved successfully');
        } catch (e) {
            console.error('[Dashboard] Failed to save token:', e);
        }
    }
    
    // Check if user is authenticated
    const jwt = localStorage.getItem('jwt');
    const userId = localStorage.getItem('userId');
    
    if (!jwt || !userId) {
        console.log('[Dashboard] No authentication found, redirecting to login');
        window.location.href = '/login.html';
        return;
    }
    
    // Check if token is expired
    try {
        const payload = JSON.parse(atob(jwt.split('.')[1]));
        const isExpired = payload.exp * 1000 < Date.now();
        
        if (isExpired) {
            console.log('[Dashboard] Token expired, redirecting to login');
            localStorage.clear();
            window.location.href = '/login.html';
            return;
        }
        
        console.log('[Dashboard] Authentication valid');
        console.log('[Dashboard] Token payload:', payload);
        
        // Get user info
        const userEmail = localStorage.getItem('userEmail') || payload.email || 'User';
        const userRole = localStorage.getItem('userRole') || payload.role || '0';
        
        console.log('[Dashboard] User:', {
            id: userId,
            email: userEmail,
            role: userRole
        });
        
        // Update UI when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => updateUserUI(userEmail, userRole));
        } else {
            updateUserUI(userEmail, userRole);
        }
        
    } catch (e) {
        console.error('[Dashboard] Token validation failed:', e);
        localStorage.clear();
        window.location.href = '/login.html';
    }
})();

/**
 * Update user interface with user information
 */
function updateUserUI(email, role) {
    console.log('[Dashboard] Updating UI with email:', email, 'role:', role);
    
    // Update user name
    const userNameElement = document.querySelector('.dashboard-user-name');
    if (userNameElement) {
        // Extract name from email (before @)
        const displayName = email.split('@')[0];
        userNameElement.textContent = displayName.charAt(0).toUpperCase() + displayName.slice(1);
    }
    
    // Update user meta (VIP level based on role)
    const userMetaElement = document.querySelector('.dashboard-user-meta');
    if (userMetaElement) {
        if (role === '1' || role === 1) {
            userMetaElement.textContent = 'VIP Level: Administrator';
        } else {
            userMetaElement.textContent = 'VIP Level: Regular User';
        }
    }
    
    // Update any email display elements
    const emailElements = document.querySelectorAll('[data-user-email]');
    emailElements.forEach(el => {
        el.textContent = email;
    });
    
    console.log('[Dashboard] UI updated successfully');
}

/**
 * Logout function
 */
function logout() {
    console.log('[Dashboard] Logging out...');
    
    // Clear all localStorage
    localStorage.clear();
    
    // Redirect to login page
    window.location.href = '/login.html';
}

/**
 * Get current user data
 */
function getCurrentUser() {
    const jwt = localStorage.getItem('jwt');
    if (!jwt) return null;
    
    try {
        const payload = JSON.parse(atob(jwt.split('.')[1]));
        return {
            id: localStorage.getItem('userId') || payload.id,
            email: localStorage.getItem('userEmail') || payload.email,
            role: localStorage.getItem('userRole') || payload.role,
            token: jwt
        };
    } catch (e) {
        console.error('[Dashboard] Failed to parse user data:', e);
        return null;
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    const user = getCurrentUser();
    return user && (user.role === '1' || user.role === 1);
}

// Export functions for global use
window.logout = logout;
window.getCurrentUser = getCurrentUser;
window.isAdmin = isAdmin;