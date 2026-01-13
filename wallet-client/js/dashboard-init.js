// dashboard-init.js
// Add this at the TOP of your dashboard.html before other scripts

(function initializeDashboard() {
    console.log('[Dashboard] ========================================');
    console.log('[Dashboard] Initializing...');
    console.log('[Dashboard] Current URL:', window.location.href);
    
    // Check URL parameters first
    const urlParams = new URLSearchParams(window.location.search);
    const tokenFromUrl = urlParams.get('token');
    const userIdFromUrl = urlParams.get('userId');
    const emailFromUrl = urlParams.get('userEmail');
    const roleFromUrl = urlParams.get('userRole');
    
    console.log('[Dashboard] URL Parameters:');
    console.log('  - token:', tokenFromUrl ? 'YES (length: ' + tokenFromUrl.length + ')' : 'NO');
    console.log('  - userId:', userIdFromUrl);
    console.log('  - userEmail:', emailFromUrl);
    console.log('  - userRole:', roleFromUrl);
    
    // If token is in URL, save it to localStorage and clean URL
    if (tokenFromUrl) {
        console.log('[Dashboard] ✅ Token found in URL, saving to localStorage');
        
        try {
            localStorage.setItem('jwt', tokenFromUrl);
            console.log('[Dashboard] ✅ JWT saved to localStorage');
            
            if (userIdFromUrl) {
                localStorage.setItem('userId', userIdFromUrl);
                console.log('[Dashboard] ✅ userId saved:', userIdFromUrl);
            }
            
            if (emailFromUrl) {
                localStorage.setItem('userEmail', decodeURIComponent(emailFromUrl));
                console.log('[Dashboard] ✅ userEmail saved:', decodeURIComponent(emailFromUrl));
            }
            
            if (roleFromUrl) {
                localStorage.setItem('userRole', roleFromUrl);
                console.log('[Dashboard] ✅ userRole saved:', roleFromUrl);
            }
            
            // Clean URL (remove query parameters)
            console.log('[Dashboard] Cleaning URL...');
            window.history.replaceState({}, document.title, window.location.pathname);
            console.log('[Dashboard] ✅ URL cleaned');
            
        } catch (e) {
            console.error('[Dashboard] ❌ Failed to save token:', e);
            alert('Failed to save login session. Please try again.');
            window.location.href = '/login.html';
            return;
        }
    }
    
    // Check if user is authenticated
    const jwt = localStorage.getItem('jwt');
    const userId = localStorage.getItem('userId');
    
    console.log('[Dashboard] Checking authentication:');
    console.log('  - jwt from localStorage:', jwt ? 'YES (length: ' + jwt.length + ')' : 'NO');
    console.log('  - userId from localStorage:', userId);
    
    if (!jwt || !userId) {
        console.log('[Dashboard] ❌ No authentication found, redirecting to login');
        alert('You must be logged in to access the dashboard.');
        window.location.href = '/login.html';
        return;
    }
    
    // Check if token is expired
    try {
        console.log('[Dashboard] Validating token...');
        const payload = JSON.parse(atob(jwt.split('.')[1]));
        const isExpired = payload.exp * 1000 < Date.now();
        
        console.log('[Dashboard] Token payload:', payload);
        console.log('[Dashboard] Token expiry:', new Date(payload.exp * 1000).toLocaleString());
        console.log('[Dashboard] Current time:', new Date().toLocaleString());
        console.log('[Dashboard] Is expired:', isExpired);
        
        if (isExpired) {
            console.log('[Dashboard] ❌ Token expired, redirecting to login');
            localStorage.clear();
            alert('Your session has expired. Please log in again.');
            window.location.href = '/login.html';
            return;
        }
        
        console.log('[Dashboard] ✅ Authentication valid');
        
        // Get user info
        const userEmail = localStorage.getItem('userEmail') || payload.email || 'User';
        const userRole = localStorage.getItem('userRole') || payload.role || '0';
        
        console.log('[Dashboard] User info:');
        console.log('  - id:', userId);
        console.log('  - email:', userEmail);
        console.log('  - role:', userRole);
        
        // Update UI when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => updateUserUI(userEmail, userRole));
        } else {
            updateUserUI(userEmail, userRole);
        }
        
        console.log('[Dashboard] ✅ Initialization complete');
        console.log('[Dashboard] ========================================');
        
    } catch (e) {
        console.error('[Dashboard] ❌ Token validation failed:', e);
        console.error('[Dashboard] Error details:', e.message);
        localStorage.clear();
        alert('Invalid login session. Please log in again.');
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
        console.log('[Dashboard] ✅ Updated user name to:', displayName);
    } else {
        console.warn('[Dashboard] ⚠️ User name element not found');
    }
    
    // Update user meta (VIP level based on role)
    const userMetaElement = document.querySelector('.dashboard-user-meta');
    if (userMetaElement) {
        if (role === '1' || role === 1) {
            userMetaElement.textContent = 'VIP Level: Administrator';
        } else {
            userMetaElement.textContent = 'VIP Level: Regular User';
        }
        console.log('[Dashboard] ✅ Updated user role display');
    } else {
        console.warn('[Dashboard] ⚠️ User meta element not found');
    }
    
    // Update any email display elements
    const emailElements = document.querySelectorAll('[data-user-email]');
    emailElements.forEach(el => {
        el.textContent = email;
    });
    
    console.log('[Dashboard] ✅ UI updated successfully');
}

/**
 * Logout function
 */
function logout() {
    console.log('[Dashboard] Logging out...');
    
    // Clear all localStorage
    localStorage.clear();
    
    console.log('[Dashboard] ✅ localStorage cleared');
    
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

// Show a welcome message for debugging
console.log('[Dashboard] 🎉 Dashboard initialized successfully!');
console.log('[Dashboard] User is logged in as:', getCurrentUser()?.email || 'Unknown');