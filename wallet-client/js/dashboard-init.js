// Add this script at the top of your dashboard.html (before other scripts)
// dashboard-init.js

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
        
        // Display user info
        const userEmail = localStorage.getItem('userEmail');
        const userRole = localStorage.getItem('userRole');
        
        console.log('[Dashboard] User:', {
            id: userId,
            email: userEmail,
            role: userRole
        });
        
        // You can update UI elements here
        const userEmailElement = document.getElementById('userEmail');
        if (userEmailElement) {
            userEmailElement.textContent = userEmail || 'User';
        }
        
    } catch (e) {
        console.error('[Dashboard] Token validation failed:', e);
        localStorage.clear();
        window.location.href = '/login.html';
    }
})();

// Add logout function
function logout() {
    console.log('[Dashboard] Logging out...');
    localStorage.clear();
    window.location.href = '/login.html';
}