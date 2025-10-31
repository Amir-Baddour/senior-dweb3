// export a single object
export const addrs = {
  // legacy key for older code that still reads `MockUSDT`
  MockUSDT: "0x5FC8d32690cc91D4c39d9d3abcBD16989F875707",

  evm: {
    rpc: "http://127.0.0.1:9545",
    chainId: 31337,
    erc20: "0x5FC8d32690cc91D4c39d9d3abcBD16989F875707"
  },

  solana: {
    rpc: "https://rpc.ankr.com/solana_devnet",     
    mint: "BwWsEGJgb7MwCjdRaxPwjJvwzjJjZQZagEA3824KBPYa"
  }
};
