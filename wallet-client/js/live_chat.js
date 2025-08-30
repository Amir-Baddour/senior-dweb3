// wallet-client/js/live_chat.js
(() => {
  const API = "http://localhost/digital-wallet-plateform/wallet-server/user/v1/live_chat.php";
  const $ = (id) => document.getElementById(id);

  // Toggle the widget and focus the input when opened
  function toggleChatBox() {
    const box = $("chat-box");
    if (!box) return;
    box.classList.toggle("show");
    if (box.classList.contains("show")) {
      setTimeout(() => $("chat-input")?.focus(), 50);
    }
  }

  // Enter to send
  function handleKey(e) {
    if (e.key === "Enter") {
      e.preventDefault();
      sendFromInput();
    }
  }

  // Send using the input field's value
  function sendFromInput() {
    const input = $("chat-input");
    if (!input) return;
    const message = input.value.trim();
    if (!message) return;
    displayMessage("You", message);
    input.value = "";
    sendToBackend(message);
  }

  // Render a message bubble
  function displayMessage(sender, text) {
    const container = $("chat-messages");
    if (!container) return;
    const div = document.createElement("div");
    div.className = "chat-message";
    div.innerHTML = `<strong>${sender}:</strong> ${escapeHtml(text)}`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
  }

  // Basic HTML escaping
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  // Call backend and show reply
  async function sendToBackend(message) {
    const btn = $("chat-send");
    if (btn) btn.disabled = true;
    try {
      const res = await axios.post(API, { message });
      const reply = res?.data?.reply ?? "OK.";
      displayMessage("AI", reply);
    } catch (err) {
      console.error("[live_chat] send error:", err);
      displayMessage("AI", "Oops! Something went wrong.");
    } finally {
      if (btn) btn.disabled = false;
      $("chat-input")?.focus();
    }
  }

  // Clear chat history
  function refreshChat() {
    const container = $("chat-messages");
    if (!container) return;
    container.innerHTML = "";
    displayMessage("AI", "Chat cleared.");
  }

  // Wire up buttons and input once DOM is ready
  document.addEventListener("DOMContentLoaded", () => {
    $("chat-send")?.addEventListener("click", sendFromInput);
    $("chat-refresh")?.addEventListener("click", refreshChat);
    $("chat-input")?.addEventListener("keydown", handleKey);
  });

  // Expose globals for inline handlers in HTML
  window.toggleChatBox = toggleChatBox;
  window.handleKey = handleKey;
})();
