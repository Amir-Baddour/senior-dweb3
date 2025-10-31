// public/js/assets_solana.js (non-module)
(function () {
  const { Connection, PublicKey, clusterApiUrl } = window.solanaWeb3;

  // --- CONFIG ---
  const MINT_STR = "DtAJKJSYqnAbKnecS1VTQZGjp7MizuNpB5h3f8zfPaFr"; // your mUSDT-SPL mint
  
  let connection = null;
  
  // Store current wallet and subscription ID
  let currentOwner = null;
  let accountSubscriptionId = null;
  let balanceCheckInterval = null;
  
  // --------------

  const $btn  = document.getElementById("sc_connect");
  const $addr = document.getElementById("sc_addr");
  const $bal  = document.getElementById("sc_bal");
  const $msg  = document.getElementById("sc_msg");
  const $refresh = document.getElementById("sc_refresh");

  function say(t){ if ($msg) $msg.textContent = t; console.log("[SOL]", t); }

  // Initialize connection with a simple, reliable approach
  function initConnection() {
    try {
      // Use the most reliable RPC endpoint
      connection = new Connection('https://api.devnet.solana.com', 'confirmed');
      console.log('Connected to Solana devnet');
      return connection;
    } catch (e) {
      console.warn('Primary RPC failed, using fallback');
      connection = new Connection(clusterApiUrl('devnet'), 'confirmed');
      return connection;
    }
  }

  async function connectPhantom() {
    try {
      if (!window.solana?.isPhantom) throw new Error("Phantom not installed/enabled.");
      
      // Initialize connection
      initConnection();
      
      const { publicKey } = await window.solana.connect({ onlyIfTrusted: false });
      const owner = new PublicKey(publicKey.toString());
      
      $addr && ($addr.textContent = owner.toBase58());
      say("Connected to Phantom (Devnet).");
      
      // Store current owner and start monitoring
      currentOwner = owner;
      await refreshSplBalance(owner);
      startBalanceMonitoring(owner);
      
      // Show refresh button after connection
      if ($refresh) $refresh.style.display = 'inline-block';
      
    } catch (e) { say(e.message || String(e)); }
  }

  async function refreshSplBalance(owner) {
    if (!MINT_STR || MINT_STR.startsWith("PUT_")) {
      $bal && ($bal.textContent = "0");
      say("Set MINT_STR in assets_solana.js"); 
      return;
    }

    say("Fetching balance from blockchain...");
    
    // Method 1: Try PHP backend proxy first (recommended)
    try {
      // Use the correct relative path
      let apiUrl = 'http://localhost/digital-wallet-plateform/wallet-server/web03/hardhat/api/solana-balance.php';
      
      console.log('Calling backend proxy at:', apiUrl);
      
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          owner: owner.toBase58(),
          mint: MINT_STR
        })
      });

      console.log('Backend proxy response status:', response.status);

      if (response.ok) {
        const data = await response.json();
        console.log('Backend proxy response:', data);
        
        if (data.success) {
          $bal && ($bal.textContent = `${data.balance} mUSDT-SPL`);
          say(`✅ Balance updated: ${data.balance} tokens (via backend)`);
          return; // Success!
        } else {
          console.warn('Backend proxy failed:', data.error);
          say(`Backend error: ${data.error || 'Unknown error'}`);
        }
      } else {
        const errorText = await response.text();
        console.warn('Backend proxy HTTP error:', response.status, errorText);
        say(`Backend HTTP error: ${response.status}`);
      }
      
    } catch (backendError) {
      console.warn('Backend proxy failed:', backendError);
      console.error('Full backend error:', backendError);
      say(`Backend proxy error: ${backendError.message}`);
    }
    
    // Method 2: Try direct RPC call with Solana Web3.js (fallback)
    if (!connection) {
      initConnection();
    }
    
    try {
      const tokenAccounts = await connection.getParsedTokenAccountsByOwner(
        owner,
        { mint: new PublicKey(MINT_STR) }
      );

      if (tokenAccounts.value.length === 0) {
        $bal && ($bal.textContent = "0 mUSDT-SPL");
        say("No token account found for this mint.");
        return;
      }

      const tokenAccount = tokenAccounts.value[0];
      const balance = tokenAccount.account.data.parsed.info.tokenAmount.uiAmount;
      
      $bal && ($bal.textContent = `${balance} mUSDT-SPL`);
      say(`✅ Balance updated: ${balance} tokens (direct)`);
      return; // Success!
      
    } catch (rpcError) {
      console.warn('Web3.js RPC failed:', rpcError);
    }
    
    // Method 3: Try direct fetch to RPC endpoint
    try {
      const rpcPayload = {
        jsonrpc: "2.0",
        id: 1,
        method: "getParsedTokenAccountsByOwner",
        params: [
          owner.toBase58(),
          { mint: MINT_STR },
          { encoding: "jsonParsed" }
        ]
      };

      const response = await fetch('https://api.devnet.solana.com', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(rpcPayload)
      });

      if (response.ok) {
        const data = await response.json();
        
        if (data.result && data.result.value.length > 0) {
          const balance = data.result.value[0].account.data.parsed.info.tokenAmount.uiAmount;
          $bal && ($bal.textContent = `${balance} mUSDT-SPL`);
          say(`✅ Balance updated: ${balance} tokens (direct fetch)`);
          return; // Success!
        } else {
          $bal && ($bal.textContent = "0 mUSDT-SPL");
          say("No token account found (direct fetch).");
          return;
        }
      }
      
    } catch (fetchError) {
      console.warn('Direct fetch failed:', fetchError);
    }
    
    // All methods failed - show error message
    $bal && ($bal.textContent = "❌ Unable to fetch");
    say("❌ All methods failed. Backend proxy needed or use command: spl-token balance DtAJKJSYqnAbKnecS1VTQZGjp7MizuNpB5h3f8zfPaFr --owner 9vBy33uiC227aePXhxuSLzSQAnt6ydtRsqYWaNbEXjtQ --url devnet");
  }

  function startBalanceMonitoring(owner) {
    // Clear any existing monitoring
    stopBalanceMonitoring();
    
    // Method 1: WebSocket subscription for real-time updates (if RPC works)
    try {
      connection.getParsedTokenAccountsByOwner(
        owner,
        { mint: new PublicKey(MINT_STR) }
      ).then(tokenAccounts => {
        if (tokenAccounts.value.length > 0) {
          const tokenAccountPubkey = tokenAccounts.value[0].pubkey;
          
          // Subscribe to account changes
          accountSubscriptionId = connection.onAccountChange(
            tokenAccountPubkey,
            (accountInfo, context) => {
              console.log('Token account changed, refreshing balance...');
              refreshSplBalance(owner);
            },
            'confirmed'
          );
          
          say("Real-time monitoring started");
        }
      }).catch(e => {
        console.log('WebSocket monitoring not available due to RPC restrictions');
      });
    } catch (e) {
      console.warn('WebSocket subscription failed:', e);
    }

    // Method 2: Fallback polling every 30 seconds
    balanceCheckInterval = setInterval(() => {
      if (currentOwner) {
        console.log('Periodic balance check...');
        refreshSplBalance(currentOwner);
      }
    }, 30000); // Check every 30 seconds
  }

  function stopBalanceMonitoring() {
    // Remove WebSocket subscription
    if (accountSubscriptionId !== null) {
      try {
        connection.removeAccountChangeListener(accountSubscriptionId);
      } catch (e) {
        console.warn('Error removing account listener:', e);
      }
      accountSubscriptionId = null;
    }
    
    // Clear polling interval
    if (balanceCheckInterval) {
      clearInterval(balanceCheckInterval);
      balanceCheckInterval = null;
    }
  }

  // Manual refresh function
  async function manualRefresh() {
    if (currentOwner) {
      say("🔄 Manually refreshing balance...");
      await refreshSplBalance(currentOwner);
    } else {
      say("Please connect wallet first");
    }
  }

  // Event listeners
  $btn && $btn.addEventListener("click", connectPhantom);
  $refresh && $refresh.addEventListener("click", manualRefresh);

  // Cleanup on page unload
  window.addEventListener('beforeunload', stopBalanceMonitoring);

  // Auto-refresh every 2 minutes as backup
  setInterval(() => {
    if (currentOwner) {
      console.log('Auto-refresh balance check...');
      refreshSplBalance(currentOwner);
    }
  }, 120000); // 2 minutes

})();