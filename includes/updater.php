<?php
/**
 * 系统在线更新模块
 * 
 * 功能：
 * - 扫描本地所有文件生成 MD5 清单
 * - 对比远程版本清单，找出新增/修改/删除的文件
 * - 逐文件更新替换
 * - SQL 迁移自动执行
 * - 更新前自动备份
 */

class Updater
{
    /** 项目根目录 */
    private string $rootDir;

    /** 更新源（本地路径 或 远程URL） */
    private string $releaseDir;

    /** 是否是远程更新源 */
    private bool $isRemote;

    /** 需要排除的目录/文件（不参与更新对比） */
    private array $excludePatterns = [
        'config/database.php',       // 各环境的数据库配置不同
        'config/app.php',            // 各环境的站点配置不同
        'storage/worker_pids/*',
        'storage/app.log',
        'storage/cron_last_scan.txt',
        'storage/installed.lock',
        '.user.ini',
        '.agents/*',
        'releases/*',
        'tools/*',
    ];

    /** 已执行的迁移记录文件 */
    private string $migrationLogFile;

    public function __construct(?string $rootDir = null, ?string $releaseDir = null)
    {
        $this->rootDir    = $rootDir    ?: dirname(__DIR__);
        // 从配置文件读取更新源，未配置则用默认值
        if ($releaseDir) {
            $this->releaseDir = $releaseDir;
        } else {
            $config = require __DIR__ . '/../config/app.php';
            $this->releaseDir = $config['update_source'] ?? '/www/wwwroot/releases_geo';
        }
        $this->isRemote = (bool)preg_match('#^https?://#', $this->releaseDir);
        $this->releaseDir = rtrim($this->releaseDir, '/');
        $this->migrationLogFile = $this->rootDir . '/storage/.migrations_log.json';
    }

    // ==================== 工具方法 ====================

    /**
     * 获取远程资源内容（支持本地文件和 HTTP URL）
     */
    private function fetchContent(string $path): ?string
    {
        $url = $this->isRemote ? $this->releaseDir . '/' . ltrim($path, '/') : $this->releaseDir . '/' . ltrim($path, '/');
        
        if ($this->isRemote) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout'    => 30,
                    'user_agent' => 'GEO-Updater/1.0',
                ],
            ]);
            $content = @file_get_contents($url, false, $ctx);
            return $content !== false ? $content : null;
        }
        
        if (!file_exists($url)) return null;
        return file_get_contents($url);
    }

    // ==================== 扫描与对比 ====================

    /**
     * 扫描项目所有文件，返回 相对路径 => MD5 的关联数组
     */
    public function scanLocalFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $absPath = $file->getRealPath();
            $relPath = str_replace($this->rootDir . '/', '', $absPath);

            if ($this->isExcluded($relPath)) continue;

            $files[$relPath] = md5_file($absPath);
        }

        ksort($files);
        return $files;
    }

    /**
     * 读取远程版本清单（支持本地文件或 HTTP URL）
     */
    public function getRemoteManifest(): ?array
    {
        $json = $this->fetchContent('version.json');
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }

    /**
     * 对比本地与远程，返回差异报告
     *
     * @return array{
     *   remote_version: string,
     *   local_version: string|null,
     *   has_update: bool,
     *   added: array,       // 新增文件列表
     *   modified: array,    // 修改文件列表
     *   removed: array,     // 需删除的文件列表
     *   migrations: array,  // 待执行的 SQL 迁移
     *   changelog: string,
     * }
     */
    public function compare(): array
    {
        $remote = $this->getRemoteManifest();
        $local  = $this->scanLocalFiles();
        $localVersion  = $this->getCurrentVersion();
        $appliedMigrations = $this->getAppliedMigrations();

        if (!$remote) {
            return [
                'remote_version' => 'N/A',
                'local_version'  => $localVersion,
                'has_update'     => false,
                'added'          => [],
                'modified'       => [],
                'removed'        => [],
                'migrations'     => [],
                'changelog'      => '',
                'error'          => '无法读取远程版本清单，请先执行发布脚本。',
            ];
        }

        $remoteFiles   = $remote['files'] ?? [];
        $remoteRemoved = $remote['removed'] ?? [];
        $remoteVersion = $remote['version'] ?? 'unknown';

        $added    = [];
        $modified = [];
        $removed  = [];

        // 找出新增和修改的文件
        foreach ($remoteFiles as $path => $remoteMd5) {
            if (!isset($local[$path])) {
                $added[] = $path;
            } elseif ($local[$path] !== $remoteMd5) {
                $modified[] = $path;
            }
        }

        // 找出需要删除的文件（远程标记为删除 + 本地存在）
        foreach ($remoteRemoved as $path) {
            if (isset($local[$path])) {
                $removed[] = $path;
            }
        }

        // 找出待执行的 SQL 迁移
        $pendingMigrations = [];
        foreach (($remote['migrations'] ?? []) as $migration) {
            if (!in_array($migration['file'], $appliedMigrations)) {
                $pendingMigrations[] = $migration;
            }
        }

        $hasUpdate = count($added) > 0 || count($modified) > 0
                  || count($removed) > 0 || count($pendingMigrations) > 0;

        return [
            'remote_version' => $remoteVersion,
            'local_version'  => $localVersion,
            'has_update'     => $hasUpdate,
            'added'          => $added,
            'modified'       => $modified,
            'removed'        => $removed,
            'migrations'     => $pendingMigrations,
            'changelog'      => $remote['changelog'] ?? '',
            'release_date'   => $remote['release_date'] ?? '',
        ];
    }

    // ==================== 执行更新 ====================

    /**
     * 执行完整更新流程（备份 → 文件更新 → 删除废弃 → SQL迁移 → 写版本）
     *
     * @return array{success: bool, steps: array, error?: string}
     */
    public function doUpdate(): array
    {
        $diff = $this->compare();

        if (!$diff['has_update']) {
            return ['success' => true, 'steps' => [['type' => 'info', 'message' => '已是最新版本，无需更新']]];
        }

        $steps   = [];
        $success = true;

        // 步骤 1：备份
        $backupResult = $this->backup();
        $steps[] = $backupResult;
        if (!$backupResult['success']) {
            return ['success' => false, 'steps' => $steps, 'error' => '备份失败，更新已中止'];
        }

        // 步骤 2：更新新增和修改的文件
        foreach (array_merge($diff['added'], $diff['modified']) as $path) {
            $result = $this->applyFile($path);
            $steps[] = $result;
            if (!$result['success']) {
                $success = false;
                break;
            }
        }

        // 步骤 3：删除废弃文件
        if ($success) {
            foreach ($diff['removed'] as $path) {
                $result = $this->removeFile($path);
                $steps[] = $result;
                if (!$result['success']) {
                    $success = false;
                    break;
                }
            }
        }

        // 步骤 4：执行 SQL 迁移
        if ($success) {
            foreach ($diff['migrations'] as $migration) {
                $result = $this->executeMigration($migration);
                $steps[] = $result;
                if (!$result['success']) {
                    $success = false;
                    break;
                }
            }
        }

        // 步骤 5：写入新版本号
        if ($success) {
            $this->setCurrentVersion($diff['remote_version']);
            $steps[] = ['type' => 'success', 'file' => '', 'success' => true,
                'message' => "版本已更新至 {$diff['remote_version']}"];
        }

        return [
            'success' => $success,
            'steps'   => $steps,
            'version' => $diff['remote_version'],
            'error'   => $success ? null : '部分操作失败，请查看详细日志',
        ];
    }

    // ==================== 单文件操作 ====================

    /**
     * 从更新源获取文件并写入目标（支持本地复制和 HTTP 下载）
     */
    private function applyFile(string $relPath): array
    {
        $target = $this->rootDir . '/' . $relPath;

        // 确保目标目录存在
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        if ($this->isRemote) {
            // 远程：HTTP 下载
            $content = $this->fetchContent('files/' . $relPath);
            if ($content === null) {
                return ['type' => 'error', 'file' => $relPath, 'success' => false,
                    'message' => "下载失败: {$relPath}"];
            }
            if (@file_put_contents($target, $content) !== false) {
                return ['type' => 'file', 'file' => $relPath, 'success' => true,
                    'message' => "已下载: {$relPath}"];
            }
        } else {
            // 本地：文件复制
            $source = $this->releaseDir . '/files/' . $relPath;
            if (!file_exists($source)) {
                return ['type' => 'error', 'file' => $relPath, 'success' => false,
                    'message' => "源文件不存在: {$relPath}"];
            }
            if (@copy($source, $target)) {
                return ['type' => 'file', 'file' => $relPath, 'success' => true,
                    'message' => "已更新: {$relPath}"];
            }
        }

        return ['type' => 'error', 'file' => $relPath, 'success' => false,
            'message' => "写入失败: {$relPath}，请检查目录权限"];
    }

    /**
     * 删除废弃文件
     */
    private function removeFile(string $relPath): array
    {
        $target = $this->rootDir . '/' . $relPath;

        if (!file_exists($target)) {
            return ['type' => 'remove', 'file' => $relPath, 'success' => true,
                'message' => "文件不存在，跳过: {$relPath}"];
        }

        if (@unlink($target)) {
            return ['type' => 'remove', 'file' => $relPath, 'success' => true,
                'message' => "已删除: {$relPath}"];
        }

        return ['type' => 'error', 'file' => $relPath, 'success' => false,
            'message' => "删除失败: {$relPath}"];
    }

    /**
     * 执行单个 SQL 迁移（支持本地文件和 HTTP 下载）
     */
    private function executeMigration(array $migration): array
    {
    $sql = $this->fetchContent('migrations/' . $migration['file']);
    if ($sql === null) {
        return ['type' => 'migration', 'file' => $migration['file'], 'success' => false,
            'message' => "迁移文件获取失败: {$migration['file']}"];
    }
    if (empty(trim($sql))) {
            return ['type' => 'migration', 'file' => $migration['file'], 'success' => true,
                'message' => "空迁移，跳过: {$migration['file']}"];
        }

        try {
            // 按分号拆分多条 SQL
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s) && !str_starts_with($s, '--') && !str_starts_with($s, '#')
            );

            foreach ($statements as $stmt) {
                dbExecute($stmt);
            }

            // 记录已执行
            $this->markMigrationApplied($migration['file']);

            $desc = $migration['description'] ?? $migration['file'];
            return ['type' => 'migration', 'file' => $migration['file'], 'success' => true,
                'message' => "SQL 迁移完成: {$desc}"];
        } catch (Exception $e) {
            return ['type' => 'migration', 'file' => $migration['file'], 'success' => false,
                'message' => "SQL 迁移失败: {$migration['file']} — " . $e->getMessage()];
        }
    }

    // ==================== 备份 ====================

    /**
     * 备份当前项目到 releases 目录
     */
    private function backup(): array
    {
        $backupDir = $this->releaseDir . '/backups/' . date('Ymd_His');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $files = $this->scanLocalFiles();
        $count = 0;

        foreach ($files as $relPath => $md5) {
            $source = $this->rootDir . '/' . $relPath;
            $target = $backupDir . '/' . $relPath;
            $targetDir = dirname($target);

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            if (@copy($source, $target)) {
                $count++;
            }
        }

        return [
            'type'    => 'backup',
            'file'    => '',
            'success' => true,
            'message' => "备份完成：{$count} 个文件 → {$backupDir}",
        ];
    }

    // ==================== 版本号管理 ====================

    /**
     * 获取当前安装的版本号
     */
    public function getCurrentVersion(): string
    {
        $file = $this->rootDir . '/storage/version.txt';
        if (file_exists($file)) {
            return trim(file_get_contents($file));
        }
        return '0.1.0-dev';
    }

    /**
     * 写入版本号
     */
    private function setCurrentVersion(string $version): void
    {
        $dir = $this->rootDir . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($dir . '/version.txt', $version);
    }

    // ==================== 迁移记录管理 ====================

    /**
     * 获取已执行的迁移列表
     */
    private function getAppliedMigrations(): array
    {
        if (!file_exists($this->migrationLogFile)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->migrationLogFile), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 标记迁移已执行
     */
    private function markMigrationApplied(string $file): void
    {
        $applied = $this->getAppliedMigrations();
        $applied[] = $file;
        $applied = array_unique($applied);
        $dir = dirname($this->migrationLogFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($this->migrationLogFile, json_encode($applied, JSON_PRETTY_PRINT));
    }

    // ==================== 工具方法 ====================

    /**
     * 判断路径是否需要排除
     */
    private function isExcluded(string $relPath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $relPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取备份列表
     */
    public function getBackups(): array
    {
        $backupRoot = $this->releaseDir . '/backups/';
        if (!is_dir($backupRoot)) return [];

        $backups = [];
        foreach (scandir($backupRoot, SCANDIR_SORT_DESCENDING) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $backupRoot . $entry;
            if (is_dir($path)) {
                $backups[] = [
                    'name' => $entry,
                    'time' => filemtime($path),
                    'path' => $path,
                ];
            }
        }
        return $backups;
    }
}
