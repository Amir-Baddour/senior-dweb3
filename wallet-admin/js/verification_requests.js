document.addEventListener("DOMContentLoaded", function () {
    // ✅ Use config with fallback
    const API_BASE_URL = window.ADMIN_CONFIG?.API_BASE_URL || 
        "http://localhost/digital-wallet-plateform/wallet-server/admin/v1";
    
    console.log('[verification-requests] Using API_BASE_URL:', API_BASE_URL);

    // Retrieve the admin JWT from localStorage; if missing, redirect to login.
    const token = localStorage.getItem("admin_jwt");
    if (!token) {
        console.warn('[verification-requests] No admin_jwt found, redirecting to login');
        window.location.href = "login.html";
        return;
    }
    
    // Create Axios configuration with the Authorization header.
    const axiosConfig = {
        headers: {
            "Authorization": `Bearer ${token}`,
            "Content-Type": "application/json"
        }
    };

    const tableBody = document.getElementById("verificationRequestsBody");

    // Fetch pending verification requests and update the table.
    function fetchRequests() {
        console.log('[verification-requests] Fetching requests from:', `${API_BASE_URL}/verification_requests.php`);
        
        axios.get(`${API_BASE_URL}/verification_requests.php`, axiosConfig)
            .then(response => {
                console.log('[verification-requests] Response:', response.data);
                
                if (response.data.status === "success") {
                    tableBody.innerHTML = ""; // Clear existing rows
                    
                    if (response.data.data && response.data.data.length > 0) {
                        response.data.data.forEach(request => {
                            const row = document.createElement("tr");
                            
                            // ✅ Build proper document URL
                            // Use API_BASE_URL to construct the uploads path
                            const uploadsPath = API_BASE_URL.replace('/admin/v1', '/uploads');
                            const documentUrl = `${uploadsPath}/${request.id_document}`;
                            
                            row.innerHTML = `
                                <td>${request.email || 'N/A'}</td>
                                <td><a href="${documentUrl}" target="_blank" rel="noopener noreferrer">View ID</a></td>
                                <td>
                                    <button class="approve-btn" data-user-id="${request.user_id}">Approve</button>
                                    <button class="reject-btn" data-user-id="${request.user_id}">Reject</button>
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                        addActionListeners();
                        console.log('[verification-requests] Loaded', response.data.data.length, 'requests');
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="3">No pending requests found.</td></tr>`;
                        console.log('[verification-requests] No pending requests');
                    }
                } else {
                    tableBody.innerHTML = `<tr><td colspan="3">No pending requests found.</td></tr>`;
                    console.warn('[verification-requests] Response status not success:', response.data);
                }
            })
            .catch(error => {
                console.error("[verification-requests] Error fetching requests:", error);
                
                let errorMsg = "Failed to load requests.";
                if (error.response) {
                    errorMsg += ` Server error: ${error.response.status}`;
                    console.error('[verification-requests] Server response:', error.response.data);
                } else if (error.request) {
                    errorMsg += " No response from server.";
                } else {
                    errorMsg += ` ${error.message}`;
                }
                
                tableBody.innerHTML = `<tr><td colspan="3">${errorMsg}</td></tr>`;
            });
    }

    // Add click event listeners to approve and reject buttons.
    function addActionListeners() {
        document.querySelectorAll(".approve-btn").forEach(button => {
            button.addEventListener("click", function () {
                const userId = this.dataset.userId;
                console.log('[verification-requests] Approving user:', userId);
                updateVerificationStatus(userId, 1);
            });
        });
        
        document.querySelectorAll(".reject-btn").forEach(button => {
            button.addEventListener("click", function () {
                const userId = this.dataset.userId;
                console.log('[verification-requests] Rejecting user:', userId);
                updateVerificationStatus(userId, -1);
            });
        });
    }

    // Update verification status and refresh the requests table.
    function updateVerificationStatus(user_id, is_validated) {
        if (!user_id) {
            console.error("[verification-requests] User ID missing.");
            alert("Error: User ID is missing.");
            return;
        }
        
        const action = is_validated === 1 ? "approve" : "reject";
        console.log(`[verification-requests] ${action}ing user ${user_id}...`);
        
        axios.post(
            `${API_BASE_URL}/update_verification.php`, 
            {
                user_id: user_id,
                is_validated: is_validated
            }, 
            {
                headers: {
                    "Authorization": `Bearer ${token}`,
                    "Content-Type": "application/json"
                }
            }
        )
        .then(response => {
            console.log('[verification-requests] Update response:', response.data);
            
            const data = response.data;
            const message = data.message || "Verification status updated.";
            
            if (data.status === "success") {
                alert(`✅ ${message}`);
                console.log(`[verification-requests] Successfully ${action}ed user ${user_id}`);
            } else {
                alert(`⚠️ ${message}`);
                console.warn('[verification-requests] Update failed:', data);
            }
            
            // Refresh the table after the update.
            fetchRequests();
        })
        .catch(error => {
            console.error("[verification-requests] Error updating verification status:", error);
            
            let errorMsg = "Failed to update verification status.";
            if (error.response) {
                errorMsg = error.response.data?.message || `Server error: ${error.response.status}`;
                console.error('[verification-requests] Server response:', error.response.data);
            } else if (error.request) {
                errorMsg = "No response from server. Check your connection.";
            } else {
                errorMsg = error.message;
            }
            
            alert(`❌ ${errorMsg}`);
        });
    }

    // Initial load of verification requests.
    console.log('[verification-requests] Initializing...');
    fetchRequests();
});