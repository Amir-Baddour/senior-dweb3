import { JsonRpcProvider, Contract, parseUnits, Wallet } from "ethers";

// Minimal ERC-20 ABI
const ABI_ERC20 = [
  {"inputs":[{"internalType":"address","name":"account","type":"address"}],"name":"balanceOf","outputs":[{"internalType":"uint256","name":"","type":"uint256"}],"stateMutability":"view","type":"function"},
  {"inputs":[{"internalType":"address","name":"to","type":"address"},{"internalType":"uint256","name":"amount","type":"uint256"}],"name":"transfer","outputs":[{"internalType":"bool","name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},
  {"inputs":[],"name":"decimals","outputs":[{"internalType":"uint8","name":"","type":"uint8"}],"stateMutability":"view","type":"function"}
];

const RPC       = "http://127.0.0.1:8545";
const TOKEN     = "0xDc64a140Aa3E981100a9becA4E685f962f0cF6C9"; // MockUSDT (update if redeployed)
const RECIPIENT = "0x98350Ed632dd79b063F69d62A828F5eA2F17227E"; // YOUR MetaMask

const provider = new JsonRpcProvider(RPC);

async function main() {
  // --- Option 1: Impersonate the first local account (address string, not signer) ---
  const accounts = await provider.send("eth_accounts", []); // returns string[]
  const deployerAddr = accounts[0];                         // e.g., 0xf39F...2266

  // Impersonate requires a **string** address:
  await provider.send("hardhat_impersonateAccount", [deployerAddr]);
  const signer = await provider.getSigner(deployerAddr);

  const usdt = new Contract(TOKEN, ABI_ERC20, signer);

  console.log("Before:", (await usdt.balanceOf(RECIPIENT)).toString());
  const tx = await usdt.transfer(RECIPIENT, parseUnits("500", 18));
  await tx.wait();
  console.log("After :", (await usdt.balanceOf(RECIPIENT)).toString());

  await provider.send("hardhat_stopImpersonatingAccount", [deployerAddr]);

  // --- Option 2 (no impersonation): use the well-known Hardhat PK (dev only) ---
  // const HH_PK0 = "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";
  // const pkSigner = new Wallet(HH_PK0, provider);
  // const usdt2 = new Contract(TOKEN, ABI_ERC20, pkSigner);
  // await usdt2.transfer(RECIPIENT, parseUnits("500", 18));
}

main().catch((e) => { console.error(e); process.exit(1); });
