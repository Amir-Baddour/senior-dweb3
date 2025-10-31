// js/src/onchain.js
// Uses window.ethers (UMD) and dynamic ESM imports for Solana libs.

export async function setupEvm(rpcUrl, expectedChainId) {
  if (!window.ethereum) throw new Error("MetaMask not found.");
  // Ask to connect
  const provider = new window.ethers.BrowserProvider(window.ethereum);
  await provider.send("eth_requestAccounts", []);
  const network = await provider.getNetwork();

  // If user is on the wrong network, try to switch
  const chainIdHex = "0x" + expectedChainId.toString(16);
  if (Number(network.chainId) !== expectedChainId) {
    try {
      await window.ethereum.request({
        method: "wallet_switchEthereumChain",
        params: [{ chainId: chainIdHex }]
      });
    } catch (e) {
      // If not added, add it
      if (e.code === 4902) {
        await window.ethereum.request({
          method: "wallet_addEthereumChain",
          params: [{
            chainId: chainIdHex,
            chainName: `Hardhat ${expectedChainId}`,
            rpcUrls: [rpcUrl],
            nativeCurrency: { name: "ETH", symbol: "ETH", decimals: 18 }
          }]
        });
      } else {
        throw e;
      }
    }
  }

  const signer = await provider.getSigner();
  const address = await signer.getAddress();
  return { provider, signer, address };
}

export async function readErc20Balance(signer, tokenAddress, erc20Abi) {
  const token = new window.ethers.Contract(tokenAddress, erc20Abi, signer);
  const [dec, raw] = await Promise.all([token.decimals(), token.balanceOf(await signer.getAddress())]);
  const human = Number(window.ethers.formatUnits(raw, dec));
  return { decimals: dec, raw, human };
}

export async function transferErc20(signer, tokenAddress, erc20Abi, to, amountWhole, decimals) {
  const token = new window.ethers.Contract(tokenAddress, erc20Abi, signer);
  const value = window.ethers.parseUnits(String(amountWhole), decimals);
  const tx = await token.transfer(to, value);
  return await tx.wait();
}

// --------- Solana ----------
export async function setupSolana(rpcUrl) {
  if (!window.solana || !window.solana.isPhantom) throw new Error("Phantom not found.");
  const { Connection, clusterApiUrl } = await import("https://esm.sh/@solana/web3.js@1.93.0");
  const connection = new Connection(rpcUrl, "confirmed");
  const { publicKey } = await window.solana.connect();
  return { Connection, connection, publicKey };
}

export async function readSplBalance(connection, ownerPubkey, mintAddress) {
  const { PublicKey } = await import("https://esm.sh/@solana/web3.js@1.93.0");
  const { getAssociatedTokenAddress, getAccount, getMint } = await import("https://esm.sh/@solana/spl-token@0.4.7");

  const mint = new PublicKey(mintAddress);
  const ata = await getAssociatedTokenAddress(mint, ownerPubkey);
  let decimals = 6, amount = 0;
  try {
    const mintInfo = await getMint(connection, mint);
    decimals = mintInfo.decimals;
    const acc = await getAccount(connection, ata);
    amount = Number(acc.amount) / Math.pow(10, decimals);
  } catch {
    // no ATA or no balance yet
    amount = 0;
  }
  return { decimals, amount };
}

export async function transferSpl(connection, owner, toPubkeyBase58, mintAddress, amountWhole) {
  const { PublicKey, SystemProgram } = await import("https://esm.sh/@solana/web3.js@1.93.0");
  const { getAssociatedTokenAddress, createTransferInstruction, getMint } = await import("https://esm.sh/@solana/spl-token@0.4.7");

  const mint = new PublicKey(mintAddress);
  const to = new PublicKey(toPubkeyBase58);
  const decimals = (await import("https://esm.sh/@solana/spl-token@0.4.7")).then(async ({ getMint }) => (await getMint(connection, mint)).decimals);
  const dec = await decimals;

  const fromAta = await getAssociatedTokenAddress(mint, owner);
  const toAta   = await getAssociatedTokenAddress(mint, to);
  const amount  = BigInt(Math.floor(Number(amountWhole) * 10 ** dec));

  const ix = createTransferInstruction(fromAta, toAta, owner, amount);
  const { sendTransaction } = window.solana; // Phantom signer
  const { Transaction } = await import("https://esm.sh/@solana/web3.js@1.93.0");
  const tx = new Transaction().add(ix);
  const sig = await window.solana.signAndSendTransaction(tx);
  return sig;
}
