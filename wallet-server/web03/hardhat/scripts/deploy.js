import hre from "hardhat";
import { ethers } from "ethers";

async function main() {
  console.log("🚀 Starting deployment...");
  
  // Connect to local network
  const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
  const signer = await provider.getSigner();
  const deployerAddress = await signer.getAddress();
  
  console.log("Deploying contracts with account:", deployerAddress);
  console.log("Account balance:", ethers.formatEther(await provider.getBalance(deployerAddress)), "ETH");

  // Get compiled contract artifacts
  const Counter = await hre.artifacts.readArtifact("Counter");
  const MockUSDT = await hre.artifacts.readArtifact("MockUSDT");

  console.log("\n📝 Contract artifacts loaded:");
  console.log("- Counter contract found");
  console.log("- MockUSDT contract found");

  // Deploy Counter contract
  console.log("\n🔨 Deploying Counter contract...");
  const counterFactory = new ethers.ContractFactory(Counter.abi, Counter.bytecode, signer);
  const counter = await counterFactory.deploy();
  await counter.waitForDeployment();
  const counterAddress = await counter.getAddress();
  
  console.log("✅ Counter deployed to:", counterAddress);

  // Deploy MockUSDT contract
  console.log("\n🔨 Deploying MockUSDT contract...");
  const usdtFactory = new ethers.ContractFactory(MockUSDT.abi, MockUSDT.bytecode, signer);
  const usdt = await usdtFactory.deploy();
  await usdt.waitForDeployment();
  const usdtAddress = await usdt.getAddress();
  
  console.log("✅ MockUSDT deployed to:", usdtAddress);

  // Test Counter contract
  console.log("\n🧪 Testing Counter contract...");
  console.log("Initial x value:", await counter.x());
  
  const incrementTx = await counter.inc();
  await incrementTx.wait();
  console.log("x value after increment:", await counter.x());

  // Test MockUSDT contract (if it has standard functions)
  console.log("\n🧪 Testing MockUSDT contract...");
  try {
    const totalSupply = await usdt.totalSupply();
    console.log("USDT Total Supply:", ethers.formatUnits(totalSupply, 6)); // USDT typically has 6 decimals
    
    const balance = await usdt.balanceOf(deployerAddress);
    console.log("Deployer USDT Balance:", ethers.formatUnits(balance, 6));
  } catch (error) {
    console.log("USDT contract functions may be different:", error.message);
  }

  console.log("\n🎉 Deployment completed!");
  console.log("📋 Contract Addresses:");
  console.log("   Counter:", counterAddress);
  console.log("   MockUSDT:", usdtAddress);
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error("❌ Deployment failed:", error);
    process.exit(1);
  });