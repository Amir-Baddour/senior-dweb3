function logout() {
    // Remove the JWT token from localStorage
    localStorage.removeItem("jwt");
    // Optionally remove other user info if you stored it
    localStorage.removeItem("userEmail");
    localStorage.removeItem("userId");
    localStorage.removeItem("userRole");
    
    // Redirect to the login page
    window.location.href = "login.html";
  }
  