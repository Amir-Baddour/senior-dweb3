<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../../utils/verify_jwt.php';
require_once __DIR__ . '/../../utils/eth_erc20.php';

// --- JWT ---
$h=getallheaders();
if(!isset($h['Authorization'])){ echo json_encode(['ok'=>false,'error'=>'no auth']); exit; }
if(!preg_match('/Bearer\s(\S+)/',$h['Authorization'],$m)){ echo json_encode(['ok'=>false,'error'=>'bad auth']); exit; }
$jwt_secret="CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded=verify_jwt($m[1],$jwt_secret);
if(!$decoded){ echo json_encode(['ok'=>false,'error'=>'invalid token']); exit; }

$action=$_GET['action'] ?? ($_POST['action'] ?? 'meta');

try{
  if($action==='meta'){ echo json_encode(['ok'=>true,'data'=>erc20_meta()]); exit; }

  if($action==='balance'){
    $addr=$_GET['address'] ?? '';
    echo json_encode(['ok'=>true,'data'=>erc20_balanceOf($addr)]); exit;
  }

  if($action==='transfer'){
    $data=json_decode(file_get_contents('php://input'),true) ?? $_POST;
    $from=$data['from']??''; $to=$data['to']??''; $amount=$data['amount']??'0';
    $tx=erc20_build_transfer($from,$to,$amount);
    echo json_encode(['ok'=>true,'data'=>$tx]); exit;
  }

  if($action==='approve'){
    $data=json_decode(file_get_contents('php://input'),true) ?? $_POST;
    $owner=$data['owner']??''; $spender=$data['spender']??''; $amount=$data['amount']??'0';
    $tx=erc20_build_approve($owner,$spender,$amount);
    echo json_encode(['ok'=>true,'data'=>$tx]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'unknown action']);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
