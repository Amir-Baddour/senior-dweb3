function logout() {
    localStorage.removeItem('admin_jwt');
    window.location.href = '/digital-wallet-plateform/wallet-admin/login.html';
  }
  