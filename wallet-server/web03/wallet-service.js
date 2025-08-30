// Run with: node wallet-service.js
// npm i express ethers dotenv
import express from "express";
import { readFileSync } from "fs";
import { ethers } from "ethers";
import dotenv from "dotenv";
dotenv.config();

const app = express();
app.use(express.json());

// --- config from .env ---
const RPC_URL        = process.env.RPC_URL;           // e.g. https://sepolia.infura.io/v3/KEY
const PRIVATE_KEY    = process.env.PRIVATE_KEY;       // hot wallet (testnet!)
const CONTRACT_ADDR  = process.env.EXCHANGE_ADDRESS;  // deployed USDTExchange address

if (!RPC_URL || !PRIVATE_KEY || !CONTRACT_ADDR) {
  console.error("Set RPC_URL, PRIVATE_KEY, EXCHANGE_ADDRESS in .env");
  process.exit(1);
}

// --- load ABI ---
const abi = JSON.parse(readFileSync(
  "wallet-server/web03/abi/USDTExchange.json", "utf8"
));

const provider = new ethers.JsonRpcProvider(RPC_URL);
const signer   = new ethers.Wallet(PRIVATE_KEY, provider);
const contract = new ethers.Contract(CONTRACT_ADDR, abi, signer);

// POST /usdtx/deposit { amount }   (uint256, use your smallest unit convention)
app.post("/usdtx/deposit", async (req, res) => {
  try {
    const { amount } = req.body;
    if (!amount || amount <= 0) throw new Error("amount required");
    const tx = await contract.depositUSDT(amount);
    const r  = await tx.wait();
    res.json({ ok: true, txHash: r.transactionHash });
  } catch (e) {
    res.status(400).json({ ok: false, error: e.message });
  }
});

// POST /usdtx/exchange { toCoin, usdtAmount, exchangeRate }
app.post("/usdtx/exchange", async (req, res) => {
  try {
    const { toCoin, usdtAmount, exchangeRate } = req.body;
    if (!toCoin || !usdtAmount || !exchangeRate) throw new Error("toCoin, usdtAmount, exchangeRate required");
    const tx = await contract.exchangeToCoin(String(toCoin), usdtAmount, exchangeRate);
    const r  = await tx.wait();
    res.json({ ok: true, txHash: r.transactionHash });
  } catch (e) {
    res.status(400).json({ ok: false, error: e.message });
  }
});

// GET /usdtx/balance/:addr
app.get("/usdtx/balance/:addr", async (req, res) => {
  try {
    const bal = await contract.getBalance(req.params.addr);
    res.json({ ok: true, balance: bal.toString() });
  } catch (e) {
    res.status(400).json({ ok: false, error: e.message });
  }
});

app.listen(5002, () => console.log("wallet-service listening on :5002"));
