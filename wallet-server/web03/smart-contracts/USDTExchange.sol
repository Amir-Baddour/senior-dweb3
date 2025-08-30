// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract USDTExchange {
    address public owner;
    mapping(address => uint256) public usdtBalances;

    event Exchanged(address indexed user, string toCoin, uint256 usdtAmount, uint256 receivedAmount);

    constructor() {
        owner = msg.sender;
    }

    function depositUSDT(uint256 amount) public {
        usdtBalances[msg.sender] += amount;
    }

    function exchangeToCoin(string memory toCoin, uint256 usdtAmount, uint256 exchangeRate) public {
        require(usdtBalances[msg.sender] >= usdtAmount, "Insufficient USDT balance");

        uint256 convertedAmount = usdtAmount * exchangeRate / 1e6; // simulate conversion (e.g., USDT = 6 decimals)

        usdtBalances[msg.sender] -= usdtAmount;
        emit Exchanged(msg.sender, toCoin, usdtAmount, convertedAmount);
    }

    function getBalance(address user) public view returns (uint256) {
        return usdtBalances[user];
    }
}
