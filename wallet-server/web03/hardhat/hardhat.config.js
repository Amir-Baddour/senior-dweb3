// hardhat.config.js  (ESM)
import "@nomicfoundation/hardhat-ethers";
export default {
  solidity: {
    version: "0.8.28",
    settings: { optimizer: { enabled: true, runs: 200 } },
  },
  networks: {
    localhost: {
      url: "http://127.0.0.1:8545",   // ← was 8545
      chainId: 31337,                 // helpful for tools & MetaMask
    },
  },
};
