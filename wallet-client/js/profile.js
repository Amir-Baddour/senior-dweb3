document.addEventListener("DOMContentLoaded", function () {
    // Check for JWT token; if missing, redirect to login
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Set up axios configuration with the JWT
    const axiosConfig = {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    };

    // Fetch and populate the user profile fields
    axios.get("http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_profile.php", axiosConfig)
        .then(response => {
            if (response.data.success) {
                document.getElementById("fullName").value = response.data.user.full_name || "";
                document.getElementById("dob").value = response.data.user.date_of_birth || "";
                document.getElementById("phone").value = response.data.user.phone_number || "";
                document.getElementById("street").value = response.data.user.street_address || "";
                document.getElementById("city").value = response.data.user.city || "";
                document.getElementById("country").value = response.data.user.country || "";
            } else {
                console.warn(response.data.message);
            }
        })
        .catch(error => {
            console.error("Error fetching profile:", error);
        });

    // Handle profile update form submission
    document.getElementById("profileForm").addEventListener("submit", function (e) {
        e.preventDefault();

        // Build form data from input fields
        const formData = {
            full_name: document.getElementById("fullName").value,
            date_of_birth: document.getElementById("dob").value,
            phone_number: document.getElementById("phone").value,
            street_address: document.getElementById("street").value,
            city: document.getElementById("city").value,
            country: document.getElementById("country").value
        };

        // Update profile via API call; on success, redirect to dashboard
        axios.post("http://localhost/digital-wallet-plateform/wallet-server/user/v1/update_profile.php", formData, axiosConfig)
            .then(response => {
                if (response.data.success) {
                    window.location.href = "dashboard.html";
                } else {
                    console.error("Error updating profile:", response.data.message);
                }
            })
            .catch(error => {
                console.error("Error updating profile:", error);
            });
    });
});