// transfer-usage.js
import { BrowserProvider } from "ethers";
import { makeApi } from "./api";

// 1) setup provider & signer
const provider = new BrowserProvider(window.ethereum);
await provider.send("eth_requestAccounts", []);
const signer = await provider.getSigner();
const me = await signer.getAddress();

// (optional) ensure we’re on localhost 31337
const { chainId } = await provider.getNetwork();
if (chainId !== 31337n) {
  console.warn("Switch your wallet to Hardhat local network (31337).");
}

// 2) setup axios API with your JWT
const jwt = localStorage.getItem("jwt"); // or however you store it
const api = makeApi(jwt);

// 3) read on-chain balance
const bal = await api.getBalance(me);
console.log("On-chain mUSDT:", bal.formatted);

// 4) build transfer tx (server encodes calldata & gas), then sign+send in browser
const recipient = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8"; // example (Account #1)
const txReq = await api.buildTransfer(me, recipient, "1000"); // human units, e.g. "1000"
//
// txReq looks like: { to, data, value:"0x0", gasLimit:"0x..." }
// ethers v6 accepts this shape directly:
const tx = await signer.sendTransaction(txReq);
const receipt = await tx.wait();
console.log("Transfer hash:", tx.hash, "block:", receipt.blockNumber);

// 5) (optional) record tx in your DB history
await api.recordOnchainTx(tx.hash);

// 6) fetch updated on-chain balance
const bal2 = await api.getBalance(me);
console.log("New on-chain mUSDT:", bal2.formatted);
