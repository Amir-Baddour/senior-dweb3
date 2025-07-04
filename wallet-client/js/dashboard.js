document.addEventListener('DOMContentLoaded', function() {
    // Check for JWT token; if missing, redirect to login
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Set up Axios config with Authorization header
    const axiosConfig = { headers: { 'Authorization': `Bearer ${token}` } };

    // Update header with user profile information (name and tier)
    const userNameElem = document.querySelector('.dashboard-user-name');
    const userMetaElem = document.querySelector('.dashboard-user-meta');
    axios.get('http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_profile.php', axiosConfig)
        .then(response => {
            if (response.data.success) {
                let fullName = response.data.user.full_name;
                let userTier = response.data.user.tier;
                 console.log("Profile API response:", response.data);
                if (!fullName || fullName.trim() === "") {
                    userNameElem.innerHTML = 'No name set. <a href="profile.html">Update your profile</a>';
                } else {
                    userNameElem.textContent = fullName;
                }
                userMetaElem.textContent = (!userTier || userTier.trim() === "")
                    ? 'User Level: Regular'
                    : 'User Level: ' + userTier;
            } else {
                userNameElem.textContent = "Unknown User";
                userMetaElem.textContent = "VIP Level: Unknown";
            }
        })
        .catch(() => {
            userNameElem.textContent = "Error Loading Name";
            userMetaElem.textContent = "VIP Level: Error";
        });

    // Update verification widget based on user's verification status
    const verificationWidget  = document.getElementById('verificationWidget');
    const verificationTitle   = document.getElementById('verificationTitle');
    const verificationMessage = document.getElementById('verificationMessage');
    const verificationButton  = document.getElementById('verificationButton');

    axios.get('http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_verification_status.php', axiosConfig)
        .then(response => {
            console.log("Verification API response:", response.data); 
            if (response.data.error) {
                verificationTitle.textContent = 'Error';
                verificationMessage.textContent = response.data.error;
                return;
            }
            const status = parseInt(response.data.is_validated, 10);
            verificationWidget.classList.remove('verification-pending', 'verification-approved', 'verification-rejected');
            switch (status) {
                case 0: // Pending
                    verificationWidget.classList.add('verification-pending');
                    verificationTitle.textContent   = 'Verification Pending';
                    verificationMessage.textContent = 'Your documents are under review. Please wait for approval.';
                    verificationButton.style.display = 'none';
                    break;
                case 1: // Approved
                    verificationWidget.classList.add('verification-approved');
                    verificationTitle.textContent   = 'Account Verified';
                    verificationMessage.textContent = 'Your account is verified. Enjoy full access to our services!';
                    verificationButton.style.display = 'none';
                    break;
                case -1: // Rejected
                    verificationWidget.classList.add('verification-rejected');
                    verificationTitle.textContent   = 'Verification Rejected';
                    verificationMessage.textContent = 'Unfortunately, your verification was rejected. Please resubmit.';
                    verificationButton.style.display = 'inline-block';
                    verificationButton.textContent   = 'Resubmit';
                    verificationButton.onclick = function() {
                        window.location.href = 'verification.html';
                    };
                    break;
                default:
                    verificationTitle.textContent   = 'Not Verified';
                    verificationMessage.textContent = 'No verification record found. Please verify to unlock features.';
                    verificationButton.style.display = 'inline-block';
                    verificationButton.textContent   = 'Verify Now';
                    verificationButton.onclick = function() {
                        window.location.href = 'verification.html';
                    };
                    break;
            }
        })
        .catch(() => {
           // verificationTitle.textContent = 'Error';
            //verificationMessage.textContent = 'Unable to load verification status.';
            if (verificationTitle) {
                verificationTitle.textContent = 'Error';
            }
            if (verificationMessage) {
                 verificationMessage.textContent = response.data.error;
          }

        });

    // Update wallet balance display
    const balanceAmountElem = document.getElementById('balanceAmount');
    if (balanceAmountElem) {
        axios.get('http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_balance.php', axiosConfig)
            .then(response => {
                console.log("Balance API response:", response.data);
                if (response.data.error) {
                    balanceAmountElem.textContent = `Error: ${response.data.error}`;
                } else {
                    const balance = response.data.balance !== undefined ? response.data.balance : 0;
                    balanceAmountElem.textContent = balance + ' USDT';
                }
            })
            .catch(() => {
                balanceAmountElem.textContent = 'Error Loading Balance';
            });
    }

    // Update transaction limits usage progress bars (daily, weekly, monthly)
    const dailyInfo   = document.getElementById('dailyInfo');
    const weeklyInfo  = document.getElementById('weeklyInfo');
    const monthlyInfo = document.getElementById('monthlyInfo');
    const dailyBar    = document.getElementById('dailyBar');
    const weeklyBar   = document.getElementById('weeklyBar');
    const monthlyBar  = document.getElementById('monthlyBar');

    function updateProgressBar(used, limit, barElem, infoElem) {
        const ratio   = limit > 0 ? (used / limit) : 0;
        const percent = Math.min(ratio * 100, 100);
        //barElem.style.width = percent.toFixed(2) + '%';
        //infoElem.textContent = used.toFixed(2) + ' / ' + limit.toFixed(2);
        if (barElem) {
            barElem.style.width = percent.toFixed(2) + '%';
        }
        if (infoElem) {
            infoElem.textContent = used.toFixed(2) + ' / ' + limit.toFixed(2);
        }

    }

    axios.get('http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_limits_usage.php', axiosConfig)
        .then(response => {
            console.log("Limits API response:", response.data);
            if (response.data.error) {
                dailyInfo.textContent   = 'Error';
                weeklyInfo.textContent  = 'Error';
                monthlyInfo.textContent = 'Error';
            } else {
                updateProgressBar(response.data.dailyUsed, response.data.dailyLimit, dailyBar, dailyInfo);
                updateProgressBar(response.data.weeklyUsed, response.data.weeklyLimit, weeklyBar, weeklyInfo);
                updateProgressBar(response.data.monthlyUsed, response.data.monthlyLimit, monthlyBar, monthlyInfo);
            }
        })
        .catch(() => {
            dailyInfo.textContent   = 'Error';
            weeklyInfo.textContent  = 'Error';
            monthlyInfo.textContent = 'Error';
        });
});