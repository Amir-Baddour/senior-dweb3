
function toggleChatBox() {
  const chatBox = document.getElementById("chat-box");
  chatBox.classList.toggle("show");
}

function handleKey(event) {
  if (event.key === "Enter") {
    const input = document.getElementById("chat-input");
    const message = input.value.trim();
    if (message) {
      displayMessage("You", message);
      input.value = "";
      sendToBackend(message);
    }
  }
}

function displayMessage(sender, text) {
  const container = document.getElementById("chat-messages");
  const div = document.createElement("div");
  div.classList.add("chat-message");
  div.innerHTML = `<strong>${sender}:</strong> ${text}`;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

function sendToBackend(message) {
  axios.post("http://localhost/digital-wallet-plateform/wallet-server/user/v1/live_chat.php", { message })
    .then(res => {
      const reply = res.data.reply;
      displayMessage("AI", reply);
    })
    .catch(err => {
      displayMessage("AI", "Oops! Something went wrong.");
    });
}