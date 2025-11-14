document.addEventListener("DOMContentLoaded", function () {
    // Check for JWT in localStorage and redirect to login if missing.
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    const fileInput = document.getElementById("idUpload");
    const submitBtn = document.getElementById("submitVerification");

    submitBtn.addEventListener("click", function () {
        // Exit if no file is selected
        if (!fileInput.files.length) {
            return;
        }

        // Prepare form data for document upload
        const formData = new FormData();
        formData.append("id_document", fileInput.files[0]);
        formData.append("referrer", document.referrer);

        
        // Make API call to upload document; redirect on success
        axios.post("http://localhost/digital-wallet-plateform/wallet-server/user/v1/verification.php", formData, {
            headers: { 
                "Content-Type": "multipart/form-data",
                "Authorization": `Bearer ${token}`
            }
        })
        .then(response => {
            if (response.data.status === "success") {
                window.location.href = "dashboard.html";
            }
        })
        .catch(error => {
            console.error("Upload error:", error);
        });
    });
});