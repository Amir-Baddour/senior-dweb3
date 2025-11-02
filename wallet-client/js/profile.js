document.addEventListener("DOMContentLoaded", function () {
  if (!window.APP_CONFIG) {
    console.error(
      "[profile.js] APP_CONFIG not loaded! Make sure config.js is included first."
    );
    return;
  }
  const { API_BASE_URL } = window.APP_CONFIG; // ✅ use config.js dynamic base

  // Check for JWT token; if missing, redirect to login
  const token = localStorage.getItem("jwt");
  if (!token) {
    window.location.href = "login.html";
    return;
  }

  // Set up axios configuration with the JWT
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // Fetch and populate the user profile fields
  axios
    .get(`${API_BASE_URL}/get_profile.php`, axiosConfig) // ✅ replaced localhost
    .then((response) => {
      if (response.data.success) {
        document.getElementById("fullName").value =
          response.data.user.full_name || "";
        document.getElementById("dob").value =
          response.data.user.date_of_birth || "";
        document.getElementById("phone").value =
          response.data.user.phone_number || "";
        document.getElementById("street").value =
          response.data.user.street_address || "";
        document.getElementById("city").value = response.data.user.city || "";
        document.getElementById("country").value =
          response.data.user.country || "";
      } else {
        console.warn(response.data.message);
      }
    })
    .catch((error) => console.error("Error fetching profile:", error));

  // Handle profile update form submission
  document
    .getElementById("profileForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = {
        full_name: document.getElementById("fullName").value,
        date_of_birth: document.getElementById("dob").value,
        phone_number: document.getElementById("phone").value,
        street_address: document.getElementById("street").value,
        city: document.getElementById("city").value,
        country: document.getElementById("country").value,
      };

      axios
        .post(`${API_BASE_URL}/update_profile.php`, formData, axiosConfig) // ✅ replaced localhost
        .then((response) => {
          if (response.data.success) {
            window.location.href = "dashboard.html";
          } else {
            console.error("Error updating profile:", response.data.message);
          }
        })
        .catch((error) => console.error("Error updating profile:", error));
    });
});
