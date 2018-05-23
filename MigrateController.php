<?php

namespace zabachok\migrate;

use yii\helpers\FileHelper;
use yii\base\Exception;
use yii\console\controllers\MigrateController as YiiMigrateController;
use yii\db\Migration;
use yii\helpers\Console;

class MigrateController extends YiiMigrateController
{
    /**
     * @param string $name
     * @throws Exception
     */
    public function actionCreate($name)
    {
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new Exception('The migration name should contain letters, digits, underscore and/or backslash characters only.');
        }

        list($namespace, $className) = $this->generateClassName($name);
        $migrationPath = $this->findMigrationPath($namespace);

        $file = $migrationPath . DIRECTORY_SEPARATOR . $className . '.php';
        if ($this->confirm("Create new migration '$file'?")) {
            $content = $this->generateMigrationSourceCode([
                'name' => $name,
                'className' => $className,
                'namespace' => $namespace,
            ]);
            FileHelper::createDirectory($migrationPath);
            file_put_contents($file, $content);
            $this->stdout("New migration created successfully.\n", Console::FG_GREEN);
        }
    }

    /**
     * @param string $name
     * @return array
     */
    private function generateClassName($name)
    {
        $namespace = null;
        $name = trim($name, '\\');
        if (strpos($name, '\\') !== false) {
            $namespace = substr($name, 0, strrpos($name, '\\'));
            $name = substr($name, strrpos($name, '\\') + 1);
        } else {
            if ($this->migrationPath === null) {
                $migrationNamespaces = $this->migrationNamespaces;
                $namespace = array_shift($migrationNamespaces);
            }
        }

        if ($namespace === null) {
            $class = 'm' . gmdate('ymd_His') . '_' . $name;
        } else {
            $class = 'M' . gmdate('ymdHis') . ucfirst($name);
        }

        return [$namespace, $class];
    }

    /**
     * @param null|string $namespace
     * @return string
     */
    protected function findMigrationPath($namespace): string
    {
        return $this->migrationPath . date('/Y/m');
    }

    /**
     * @inheritdoc
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $class => $time) {
            $applied[trim($class, '\\')] = true;
        }

        $migrations = [];
        foreach ($this->getMigrationPaths() as $namespace => $migrationPath) {
            if (!file_exists($migrationPath)) {
                continue;
            }
            $handle = opendir($migrationPath);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..' || !is_file($migrationPath . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }

                $directory = str_replace($this->migrationPath, '', $migrationPath);
                if ($directory == DIRECTORY_SEPARATOR) {
                    $directory = '';
                }
                if (preg_match('/^(m(\d{6}_?\d{6})\D.*?)\.php$/is', $file, $matches)) {
                    $class = $matches[1];
                    $time = str_replace('_', '', $matches[2]);
                    if (!isset($applied[$class])) {
                        $migrations[$time . '\\' . $class] = (empty($directory) ? '' : $directory . DIRECTORY_SEPARATOR) . $class;
                    }
                }
            }
            closedir($handle);
        }
        ksort($migrations);

        return array_values($migrations);
    }

    /**
     * @return array
     */
    private function getMigrationPaths(): array
    {
        $files = [];
        $files[] = $this->migrationPath;
        $files = array_merge($files, FileHelper::findDirectories($this->migrationPath, ['recursive' => true]));

        return $files;
    }

    /**
     * Upgrades with the specified migration class.
     * @param string $path the migration class name
     * @return bool whether the migration is successful
     */
    protected function migrateUp($path)
    {
        if ($path === self::BASE_MIGRATION) {
            return true;
        }

        $this->stdout("*** applying $path\n", Console::FG_YELLOW);
        $start = microtime(true);

        $class = preg_replace('|(/.*/)|u', '', $path);
        $migration = $this->createMigrationClass($path, $class);
        if ($migration->up() !== false) {
            $this->addMigrationHistory($class);
            $time = microtime(true) - $start;
            $this->stdout("*** applied $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);

            return true;
        } else {
            $time = microtime(true) - $start;
            $this->stdout("*** failed to apply $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);

            return false;
        }
    }

    /**
     * @param string $path
     * @param string $class
     * @return Migration
     */
    protected function createMigrationClass(string $path, string $class): Migration
    {
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $path . '.php';
        require_once($file);

        return new $class(['db' => $this->db]);
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return Migration the migration instance
     */
    protected function createMigration($class): Migration
    {
        preg_match('|^m(\d\d)(\d\d)|', $class, $matches);
        $path = '20' . $matches[1] . DIRECTORY_SEPARATOR . $matches[2] . DIRECTORY_SEPARATOR . $class;

        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $path . '.php';
        require_once($file);

        return new $class(['db' => $this->db]);
    }
}
