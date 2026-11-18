<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/db.php';
function current_user():?array {return $_SESSION['cd_user']??null;}
function require_login(?string $role=null):array {
 $u=current_user();if(!$u){header('Location: '.root_url().'login.php');exit;}
 if($role!==null&&$u['role']!==$role){http_response_code(403);exit('Access denied');}
 return $u;
}
function root_url():string {
 $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/index.php');
 $parts=explode('/',trim($script,'/'));$idx=array_search('CleanDrive_Integrated',$parts,true);
 if($idx!==false)return '/'.implode('/',array_slice($parts,0,$idx+1)).'/';
 // When folder is renamed to Clean_Drive, determine root by finding known role/API directories.
 foreach(['super-admin','barangay','contractor','resident','collector','gso','api'] as $dir){$pos=array_search($dir,$parts,true);if($pos!==false)return '/'.implode('/',array_slice($parts,0,$pos)).( $pos?'/':'');}
 return '/'.implode('/',array_slice($parts,0,-1)).(count($parts)>1?'/':'');
}
function csrf():string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verify_csrf():void {
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf_token']??'');
    if(!hash_equals(csrf(),$token)){http_response_code(419);throw new RuntimeException('Invalid session token. Refresh the page.');}
}
