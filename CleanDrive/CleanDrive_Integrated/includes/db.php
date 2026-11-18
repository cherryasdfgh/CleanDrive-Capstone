<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo=null;
    if (!$pdo) {
        $host=getenv('CLEANDRIVE_DB_HOST') ?: '127.0.0.1';
        $name=getenv('CLEANDRIVE_DB_NAME') ?: 'cleandrive';
        $user=getenv('CLEANDRIVE_DB_USER') ?: 'root';
        $pass=getenv('CLEANDRIVE_DB_PASS') ?: '';
        $pdo=new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    return $pdo;
}
function rows(string $sql,array $params=[]):array {$q=db()->prepare($sql);$q->execute($params);return $q->fetchAll();}
function one(string $sql,array $params=[]):?array {$q=db()->prepare($sql);$q->execute($params);return $q->fetch()?:null;}
function change(string $sql,array $params=[]):int {$q=db()->prepare($sql);$q->execute($params);return $q->rowCount();}
