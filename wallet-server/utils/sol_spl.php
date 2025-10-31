<?php
// utils/sol_spl.php — minimal Solana RPC helpers for SPL token meta & balance

if (!defined('SOL_RPC'))  define('SOL_RPC', 'https://api.devnet.solana.com');     // devnet
if (!defined('SPL_MINT')) define('SPL_MINT', 'PASTE_YOUR_DEVNET_MINT');           // your mint
if (!defined('SPL_DEC'))  define('SPL_DEC', 6);                                    // your decimals

function sol_rpc($method, $params = [])
{
    $payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    $ch = curl_init(SOL_RPC);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new RuntimeException('RPC error: ' . curl_error($ch));
    $json = json_decode($res, true);
    if (isset($json['error'])) throw new RuntimeException('RPC ' . $method . ' error: ' . $json['error']['message']);
    return $json['result'];
}

function spl_meta()
{
    return ['mint' => SPL_MINT, 'symbol' => 'mUSDT-SPL', 'decimals' => SPL_DEC, 'network' => SOL_RPC];
}

function spl_balance($ownerBase58)
{
    $accs = sol_rpc('getTokenAccountsByOwner', [$ownerBase58, ['mint' => SPL_MINT], ['encoding' => 'jsonParsed']]);
    $value = $accs['value'] ?? [];
    if (!$value) return ['mint' => SPL_MINT, 'amount' => 0, 'uiAmount' => 0.0, 'decimals' => SPL_DEC];
    $info = $value[0]['account']['data']['parsed']['info']['tokenAmount'] ?? null;
    if (!$info) return ['mint' => SPL_MINT, 'amount' => 0, 'uiAmount' => 0.0, 'decimals' => SPL_DEC];
    return [
        'mint' => SPL_MINT,
        'amount' => (int)($info['amount'] ?? 0),
        'uiAmount' => (float)($info['uiAmount'] ?? 0.0),
        'decimals' => (int)($info['decimals'] ?? SPL_DEC),
    ];
}
