<?php
// utils/eth_erc20.php — JSON-RPC + ERC20 helpers (requires BCMath + cURL)

if (!defined('RPC_URL'))    define('RPC_URL', 'http://127.0.0.1:8545'); // Hardhat node
if (!defined('ERC20_ADDR')) define('ERC20_ADDR', '0xe7f1725E7734CE288F8367e1Bb143E90bb3F0512'); // MockUSDT
if (!defined('ERC20_DEC'))  define('ERC20_DEC', 18);

if (!extension_loaded('bcmath')) { throw new RuntimeException('Enable PHP BCMath'); }

function rpc($method,$params=[]){
  $payload = json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>$params]);
  $ch = curl_init(RPC_URL);
  curl_setopt_array($ch,[
    CURLOPT_POST=>1, CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>$payload
  ]);
  $res = curl_exec($ch);
  if($res===false) throw new RuntimeException('RPC error: '.curl_error($ch));
  $json = json_decode($res,true);
  if(isset($json['error'])) throw new RuntimeException('RPC '.$method.' error: '.$json['error']['message']);
  return $json['result'];
}

function is_addr($a){ return preg_match('/^0x[0-9a-fA-F]{40}$/',$a); }
function pad32($h){ return str_pad($h,64,'0',STR_PAD_LEFT); }
function dec_to_hex($d){ $d=ltrim((string)$d,'+'); if($d==='0') return '0'; $h=''; while(bccomp($d,'0')>0){ $r=bcmod($d,'16'); $h=dechex((int)$r).$h; $d=bcdiv($d,'16',0);} return $h; }
function hex_to_dec($h){ $h=strtolower($h); $d='0'; for($i=0;$i<strlen($h);$i++){ $d=bcmul($d,'16',0); $d=bcadd($d, hexdec($h[$i]),0);} return $d; }
function enc_addr($a){ return pad32(strtolower(substr($a,2))); }
function enc_uint($dec){ return pad32(dec_to_hex($dec)); }
function parse_units_dec($human,$dec){
  $s=(string)$human; if(strpos($s,'.')===false) return bcmul($s,bcpow('10',(string)$dec,0),0);
  [$i,$f]=explode('.',$s,2); if(strlen($f)>$dec) throw new InvalidArgumentException('Too many decimals');
  $f=str_pad($f,$dec,'0'); return bcadd(bcmul($i,bcpow('10',(string)$dec,0),0),$f,0);
}

// ERC20 selectors
const SEL_NAME='06fdde03'; const SEL_SYMBOL='95d89b41'; const SEL_DECIMALS='313ce567';
const SEL_BALANCEOF='70a08231'; const SEL_TRANSFER='a9059cbb'; const SEL_APPROVE='095ea7b3';

function eth_call_str($to,$sel){
  $ret=rpc('eth_call',[[ 'to'=>$to,'data'=>"0x$sel" ],'latest']);
  $hex=substr($ret,2); $len=hexdec(substr($hex,64,64)); $data=substr($hex,128,$len*2);
  return hex2bin($data);
}
function eth_call_u256($to,$sel,$args){
  $ret=rpc('eth_call',[[ 'to'=>$to,'data'=>"0x$sel".implode('',$args) ],'latest']);
  return hex_to_dec(substr($ret,-64));
}

function erc20_meta(){
  return [
    'address'=>ERC20_ADDR,
    'name'=>eth_call_str(ERC20_ADDR,SEL_NAME),
    'symbol'=>eth_call_str(ERC20_ADDR,SEL_SYMBOL),
    'decimals'=>(int)eth_call_u256(ERC20_ADDR,SEL_DECIMALS,[])
  ];
}
function erc20_balanceOf($addr){
  if(!is_addr($addr)) throw new InvalidArgumentException('bad address');
  $wei=eth_call_u256(ERC20_ADDR,SEL_BALANCEOF,[enc_addr($addr)]);
  return ['raw'=>$wei,'formatted'=>bcdiv($wei,bcpow('10',(string)ERC20_DEC,0),ERC20_DEC)];
}

function erc20_build_transfer($from,$to,$amountHuman){
  if(!is_addr($from)||!is_addr($to)) throw new InvalidArgumentException('bad address');
  $wei=parse_units_dec($amountHuman,ERC20_DEC);
  $data='0x'.SEL_TRANSFER.enc_addr($to).enc_uint($wei);
  $gas=rpc('eth_estimateGas',[[ 'from'=>$from,'to'=>ERC20_ADDR,'data'=>$data,'value'=>'0x0' ]]);
  return ['to'=>ERC20_ADDR,'data'=>$data,'value'=>'0x0','gasLimit'=>$gas];
}
function erc20_build_approve($owner,$spender,$amountHuman){
  if(!is_addr($owner)||!is_addr($spender)) throw new InvalidArgumentException('bad address');
  $wei=parse_units_dec($amountHuman,ERC20_DEC);
  $data='0x'.SEL_APPROVE.enc_addr($spender).enc_uint($wei);
  $gas=rpc('eth_estimateGas',[[ 'from'=>$owner,'to'=>ERC20_ADDR,'data'=>$data,'value'=>'0x0' ]]);
  return ['to'=>ERC20_ADDR,'data'=>$data,'value'=>'0x0','gasLimit'=>$gas];
}
