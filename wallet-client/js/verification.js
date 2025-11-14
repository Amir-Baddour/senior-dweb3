// js/verification.js — COMPLETE FIXED VERSION
document.addEventListener("DOMContentLoaded", function () {
    // ✅ Use config with fallback
    const API_BASE_URL = window.APP_CONFIG?.API_BASE_URL || 
        'https://sixth-audit-valuable-until.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1';
    
    console.log('[verification.js] Using API_BASE_URL:', API_BASE_URL);

    // ✅ Check for JWT in localStorage or sessionStorage
    const token = localStorage.getItem('jwt') || sessionStorage.getItem('jwt');
    if (!token) {
        console.warn('[verification.js] No JWT token found, redirecting to login');
        window.location.href = 'login.html';
        return;
    }

    const fileInput = document.getElementById("idUpload");
    const submitBtn = document.getElementById("submitVerification");
    const uploadText = document.querySelector(".upload-text");

    // ✅ Show selected file name
    fileInput.addEventListener("change", function() {
        if (this.files && this.files[0]) {
            const fileName = this.files[0].name;
            const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2); // Convert to MB
            
            console.log('[verification.js] File selected:', fileName, `(${fileSize} MB)`);
            
            if (uploadText) {
                uploadText.textContent = `📄 ${fileName} (${fileSize} MB)`;
            }
        }
    });

    // ✅ Handle form submission
    submitBtn.addEventListener("click", async function () {
        console.log('[verification.js] Submit button clicked');

        // Validate file selection
        if (!fileInput.files || !fileInput.files.length) {
            alert("⚠️ Please select a file first!");
            console.warn('[verification.js] No file selected');
            return;
        }

        const file = fileInput.files[0];
        const fileSize = file.size / 1024 / 1024; // Convert to MB

        // ✅ Client-side validation
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            alert("❌ Invalid file type. Only JPG, PNG, and PDF are allowed.");
            console.error('[verification.js] Invalid file type:', file.type);
            return;
        }

        if (fileSize > 2) {
            alert("❌ File too large. Maximum size is 2MB.");
            console.error('[verification.js] File too large:', fileSize.toFixed(2), 'MB');
            return;
        }

        // ✅ Show loading state
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = "Uploading...";
        submitBtn.style.opacity = "0.6";

        console.log('[verification.js] Starting upload...');

        try {
            // Prepare form data for document upload
            const formData = new FormData();
            formData.append("id_document", file);
            formData.append("referrer", document.referrer || window.location.href);

            console.log('[verification.js] FormData prepared, making API call...');

            // ✅ Make API call to upload document
            const response = await axios.post(
                `${API_BASE_URL}/verification.php`, 
                formData, 
                {
                    headers: { 
                        "Content-Type": "multipart/form-data",
                        "Authorization": `Bearer ${token}`
                    }
                }
            );

            console.log('[verification.js] Upload response:', response.data);

            // ✅ Handle success response
            if (response.data.status === "success") {
                alert("✅ " + response.data.message);
                console.log('[verification.js] Upload successful, redirecting to dashboard...');
                
                // Small delay to show success message
                setTimeout(() => {
                    window.location.href = "dashboard.html";
                }, 500);
            } else {
                // Handle server-side error
                alert("❌ " + (response.data.message || "Upload failed. Please try again."));
                console.error('[verification.js] Server returned error:', response.data);
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                submitBtn.style.opacity = "1";
            }

        } catch (error) {
            console.error('[verification.js] Upload error:', error);

            // ✅ Handle different error types
            let errorMessage = "Upload failed. Please try again.";

            if (error.response) {
                // Server responded with error
                console.error('[verification.js] Server error response:', error.response.data);
                errorMessage = error.response.data?.message || `Server error: ${error.response.status}`;
            } else if (error.request) {
                // Request made but no response
                console.error('[verification.js] No response from server');
                errorMessage = "No response from server. Please check your connection.";
            } else {
                // Error setting up request
                console.error('[verification.js] Request setup error:', error.message);
                errorMessage = error.message;
            }

            alert("❌ " + errorMessage);

            // ✅ Reset button state
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            submitBtn.style.opacity = "1";
        }
    });

    // ✅ Optional: Add drag-and-drop support
    const uploadBox = document.querySelector(".upload-box");
    if (uploadBox) {
        uploadBox.addEventListener("dragover", function(e) {
            e.preventDefault();
            this.style.borderColor = "#4f46e5";
            this.style.backgroundColor = "#f0f0ff";
        });

        uploadBox.addEventListener("dragleave", function(e) {
            e.preventDefault();
            this.style.borderColor = "";
            this.style.backgroundColor = "";
        });

        uploadBox.addEventListener("drop", function(e) {
            e.preventDefault();
            this.style.borderColor = "";
            this.style.backgroundColor = "";

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                
                // Trigger change event
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            }
        });
    }

    console.log('[verification.js] Initialization complete');
});