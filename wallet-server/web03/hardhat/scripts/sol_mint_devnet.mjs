import {
  Connection, Keypair, PublicKey, clusterApiUrl, LAMPORTS_PER_SOL
} from "@solana/web3.js";
import {
  createMint,
  getOrCreateAssociatedTokenAccount,
  mintTo,
} from "@solana/spl-token";

// === CONFIG ===
const RPC = clusterApiUrl("devnet");
const DECIMALS = 6;                            // like USDT (6)
const RECIPIENT = new PublicKey("9vBy33uiC227aePXhxuSLzSQAnt6ydtRsqYWaNbEXjtQ"); // your Phantom
const AMOUNT_UI = 500;                         // 500 mUSDT
// =============

const AMOUNT_BASE = BigInt(AMOUNT_UI) * BigInt(10 ** DECIMALS);

async function main() {
  const connection = new Connection(RPC, "confirmed");

  // Fee payer + mint authority (fresh throwaway dev keypair)
  const payer = Keypair.generate();
  console.log("Fee payer:", payer.publicKey.toBase58());

  // Airdrop SOL for fees
  const sig = await connection.requestAirdrop(payer.publicKey, 2 * LAMPORTS_PER_SOL);
  await connection.confirmTransaction({ signature: sig, ...(await connection.getLatestBlockhash()) }, "confirmed");
  console.log("Airdropped 2 SOL on Devnet.");

  // Create mint (payer is mintAuthority + freezeAuthority for demo)
  const mint = await createMint(
    connection,
    payer,
    payer.publicKey,
    payer.publicKey,
    DECIMALS
  );
  console.log("New mUSDT-SPL mint:", mint.toBase58());

  // Get/create ATA for your Phantom wallet
  const ata = await getOrCreateAssociatedTokenAccount(connection, payer, mint, RECIPIENT);
  console.log("Recipient ATA:", ata.address.toBase58());

  // Mint tokens to your Phantom
  const tx2 = await mintTo(connection, payer, mint, ata.address, payer, AMOUNT_BASE);
  console.log("Minted", AMOUNT_UI, "mUSDT-SPL. Tx:", tx2);

  console.log("\n✅ Done. Copy this MINT into your frontend assets_solana.js:");
  console.log(mint.toBase58());
}

main().catch((e) => { console.error(e); process.exit(1); });
