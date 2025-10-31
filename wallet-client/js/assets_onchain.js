const ADDR = {
  counter:  "0x5FbDB2315678afecb367f032d93F642f64180aa3", // ✅ NEW
  mockusdt: "0xe7f1725E7734CE288F8367e1Bb143E90bb3F0512",
};
const CHAIN_HEX = "0x7a69"; // 31337

const ABI_COUNTER = [
  {"inputs":[],"name":"x","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},
  {"inputs":[],"name":"inc","outputs":[],"stateMutability":"nonpayable","type":"function"}
];
const ABI_ERC20 = [
  {"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},
  {"inputs":[],"name":"decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"stateMutability":"view","type":"function"},
  {"inputs":[],"name":"symbol","outputs":[{"internalType":"string","name":"","type":"string"}],"stateMutability":"view","type":"function"}
];

async function getMM(){
  const eth = window.ethereum;
  if(!eth) throw new Error("Install/enable MetaMask.");
  return eth.providers?.find(p=>p.isMetaMask) || (eth.isMetaMask ? eth : null);
}

async function ensureHardhat(mm){
  try {
    await mm.request({ method:'wallet_switchEthereumChain', params:[{ chainId: CHAIN_HEX }] });
  } catch (e) {
    if (e?.code === 4902 || e?.code === -32603) {
      await mm.request({ method:'wallet_addEthereumChain', params:[{
        chainId: CHAIN_HEX, chainName:'Hardhat 31337',
        nativeCurrency:{ name:'ETH', symbol:'ETH', decimals:18 },
        rpcUrls:['http://127.0.0.1:8545']
      }]});
      await mm.request({ method:'wallet_switchEthereumChain', params:[{ chainId: CHAIN_HEX }] });
    } else { throw e; }
  }
}

window.renderEvm = async function(){
  try{
    const mm = await getMM();
    if (!window.__evmListenersBound){
      mm.on('chainChanged', ()=>window.location.reload());
      mm.on('accountsChanged', ()=>window.renderEvm());
      window.__evmListenersBound = true;
    }
    await ensureHardhat(mm);

    // 1) Signer provider for writes (MetaMask)
    const browser = new window.ethers.BrowserProvider(mm, 'any');
    const signer = await browser.getSigner();
    const me = await signer.getAddress();

    // 2) Direct RPC for reads (bypasses MetaMask circuit breaker)
    const rpc = new window.ethers.JsonRpcProvider('http://127.0.0.1:8545');

    // Verify contracts exist (read via RPC)
    for (const [name, addr] of Object.entries(ADDR)){
      const code = await rpc.getCode(addr);
      if (code === '0x') throw new Error(`No ${name} at ${addr} on 31337`);
    }

    const counterRO  = new window.ethers.Contract(ADDR.counter,  ABI_COUNTER, rpc);
    const mockusdtRO = new window.ethers.Contract(ADDR.mockusdt, ABI_ERC20,  rpc);
    const counter    = counterRO.connect(signer); // write-enabled
    // const mockusdt = mockusdtRO.connect(signer); // if you need writes

    // ---- Hook to your DOM (ids from your assets.html) ----
    const $addr   = document.querySelector('#oc_addr');
    const $bal    = document.querySelector('#oc_bal');
    const $msg    = document.querySelector('#oc_msg');

    // Show token contract address
    if ($addr) $addr.textContent = ADDR.mockusdt;

    // Show ERC-20 balance (read via RPC)
    const [dec, sym, bal] = await Promise.all([
      mockusdtRO.decimals(), mockusdtRO.symbol(), mockusdtRO.balanceOf(me)
    ]);
    if ($bal) $bal.textContent = `${Number(bal) / 10**Number(dec)} ${sym}`;

    // Optional: show Counter.x and an action
    const x = await counterRO.x();
    console.log('Counter.x =', x.toString());

    // You can wire a button if you add one to the page
    // document.getElementById('incBtn')?.addEventListener('click', async ()=>{
    //   const tx = await counter.inc(); await tx.wait();
    //   console.log('Counter incremented');
    // });

    $msg && ($msg.textContent = 'Connected to Hardhat via MetaMask (writes) + RPC (reads).');
  }catch(err){
    console.error('[EVM] Error:', err);
    const $msg = document.querySelector('#oc_msg');
    $msg && ($msg.textContent = err.message || String(err));
  }
};