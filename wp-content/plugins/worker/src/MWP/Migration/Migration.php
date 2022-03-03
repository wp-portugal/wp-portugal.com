<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base component for ManageWP Worker migrations.
 * Renamed options and those that changed their format from one plugin version to another should be handled here.
 */
class MWP_Migration_Migration
{
    /**
     * Upon plugin activation this version is persisted to the database.
     * It is also checked against on every master request.
     */
    const VERSION = 2;

    /**
     * @var MWP_WordPress_Context
     */
    private $context;

    /**
     * Migration version => migration name (method).
     * The last migration index MUST be the same as the VERSION constant above.
     *
     * @var array
     */
    private static $migrations = array(
        1 => 'migrateBackupFileNames',
        2 => 'setDefaultOptionValues',
    );

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    private function acquireLock($lockName, $ttl = 3600)
    {
        $wpdb             = $this->context->getDb();
        $lockRow          = $wpdb->get_row("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = '$lockName'");
        $currentTimestamp = $this->context->getCurrentTime()->format('U');

        if ($lockRow) {
            /** @noinspection PhpUndefinedFieldInspection */
            if ((int) $lockRow->option_value + $ttl > $currentTimestamp) {
                return false;
            } else {
                $this->releaseLock($lockName);
            }
        }

        $locked = $wpdb->query("INSERT INTO {$wpdb->prefix}options SET option_name = '$lockName', option_value = '$currentTimestamp'");

        return (bool) $locked;
    }

    private function releaseLock($lockName)
    {
        $wpdb     = $this->context->getDb();
        $released = $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name = '$lockName'");

        return (bool) $released;
    }

    public function migrate()
    {
        $wpdb     = $this->context->getDb();
        $lockName = 'worker_migration_lock';

        $version        = (int) $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'worker_migration_version'");
        $currentVersion = self::VERSION;

        if ($version >= $currentVersion) {
            return;
        }

        if (!$this->acquireLock($lockName)) {
            return;
        }

        foreach (self::$migrations as $migrationVersion => $migrationName) {
            if ($version < $migrationVersion) {
                $this->$migrationName();
                $wpdb->query("INSERT INTO {$wpdb->prefix}options SET option_name = 'worker_migration_version', option_value = '$migrationVersion' ON DUPLICATE KEY UPDATE option_value = '$migrationVersion'");
            }
        }

        $this->releaseLock($lockName);
    }

    private function setDefaultOptionValues()
    {
        if (!$this->context->optionGet('mwp_recovering')) {
            $this->context->optionSet('mwp_recovering', '');
        }

        if (!$this->context->optionGet('mwp_incremental_update_active')) {
            $this->context->optionSet('mwp_incremental_update_active', '');
        }

        if (!$this->context->optionGet('mwp_core_autoupdate')) {
            $this->context->optionSet('mwp_core_autoupdate', '');
        }

        if (!$this->context->optionGet('mwp_container_parameters')) {
            $this->context->optionSet('mwp_container_parameters', array());
        }

        if (!$this->context->optionGet('mwp_container_site_parameters')) {
            $this->context->optionSet('mwp_container_site_parameters', array());
        }

        if (!$this->context->optionGet('_worker_nossl_key')) {
            $this->context->optionSet('_worker_nossl_key', '');
        }

        if (!$this->context->optionGet('_worker_public_key')) {
            $this->context->optionSet('_worker_public_key', '');
        }

        if (!$this->context->optionGet('mwp_maintenace_mode')) {
            $this->context->optionSet('mwp_maintenace_mode', array());
        }
    }

    private function migrateBackupFileNames()
    {
        $tasks   = (array) $this->context->optionGet('mwp_backup_tasks');
        $changed = false;

        foreach ($tasks as $taskName => $taskInfo) {
            if (!isset($taskInfo['task_results']) || !is_array($taskInfo['task_results'])) {
                continue;
            }
            foreach ($taskInfo['task_results'] as $resultId => $taskResult) {
                if (!isset($taskResult['server']['file_path']) || !file_exists($taskResult['server']['file_path'])) {
                    continue;
                }
                $filePath    = $taskResult['server']['file_path'];
                $hash        = $this->generateRandomHash(filesize($filePath));
                $newFilePath = preg_replace('{_[a-z0-9]{32}\.zip$}', sprintf('_%s.zip', $hash), $filePath);
                $renamed     = rename($filePath, $newFilePath);
                if (!$renamed) {
                    continue;
                }
                $changed = true;
                $oldUrl  = $tasks[$taskName]['task_results'][$resultId]['server']['file_url'];
                $newUrl  = preg_replace('{_[a-z0-9]{32}\.zip$}', sprintf('_%s.zip', $hash), $oldUrl);

                $tasks[$taskName]['task_results'][$resultId]['server']['file_path'] = $newFilePath;
                $tasks[$taskName]['task_results'][$resultId]['server']['file_url']  = $newUrl;
            }
        }

        if (!$changed) {
            return;
        }

        $this->context->optionSet('mwp_backup_tasks', $tasks);
    }

    private function generateRandomHash($seed)
    {
        return str_shuffle(md5(mt_rand().$seed));
    }
}
