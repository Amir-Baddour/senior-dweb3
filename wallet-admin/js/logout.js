function logout() {
    localStorage.removeItem('admin_jwt');
    window.location.href = '/digital-wallet-platform/wallet-admin/login.html';
  }
  