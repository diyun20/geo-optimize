<?php
 
require_once __DIR__ . '/auth_check.php';
/**
  * 数据库连接层
  * 提供 PDO 单例连接和常用查询方法
  */
 
 /**
  * 获取 PDO 数据库连接（单例）
  */
 function db(): PDO {
     static $pdo = null;
     if ($pdo === null) {
         $configFile = __DIR__ . '/../config/database.php';
         if (!file_exists($configFile)) {
             throw new RuntimeException('数据库配置不存在，请先运行安装向导。');
         }
         $db = require $configFile;
 
         $dsn = sprintf(
             'mysql:host=%s;port=%d;dbname=%s;charset=%s',
             $db['host'],
             $db['port'],
             $db['dbname'],
             $db['charset']
         );
 
         $pdo = new PDO($dsn, $db['username'], $db['password'], [
             PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES   => false,
         ]);
     }
     return $pdo;
 }
 
 /**
  * 执行查询并返回所有结果
  */
 function dbFetchAll(string $sql, array $params = []) {
     $stmt = db()->prepare($sql);
     $stmt->execute($params);
     return $stmt->fetchAll();
 }
 
 /**
  * 执行查询并返回单行
  */
 function dbFetchOne(string $sql, array $params = []) {
     $stmt = db()->prepare($sql);
     $stmt->execute($params);
     return $stmt->fetch() ?: null;
 }
 
 /**
  * 执行 INSERT/UPDATE/DELETE，返回受影响行数
  */
 function dbExecute(string $sql, array $params = []) {
     $stmt = db()->prepare($sql);
     $stmt->execute($params);
     return $stmt->rowCount();
 }
 
 /**
  * 插入并返回自增 ID
  */
 function dbInsert(string $sql, array $params = []) {
     $db = db();
     $stmt = $db->prepare($sql);
     $stmt->execute($params);
     return (int) $db->lastInsertId();
 }
