function logout() {
    localStorage.removeItem('admin_jwt');
    window.location.href = 'login.html';
  }
  