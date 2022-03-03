<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_MysqlDumpDumper implements MWP_IncrementalBackup_Database_DumperInterface
{

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var MWP_IncrementalBackup_Database_DumpOptions
     */
    private $options;

    /**
     * @param MWP_IncrementalBackup_Database_Configuration $configuration
     * @param MWP_IncrementalBackup_Database_DumpOptions   $options
     */
    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration, MWP_IncrementalBackup_Database_DumpOptions $options)
    {
        $this->configuration = $configuration;
        $this->options       = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function dump($table, $realpath)
    {
        if (!mwp_is_shell_available()) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::SHELL_NOT_AVAILABLE, 'Shell not available.');
        }

        $mysqldump      = mwp_container()->getExecutableFinder()->find('mysqldump', 'mysqldump');
        $processBuilder = $this->createProcessBuilder(array($table), $mysqldump);
        $processBuilder->add('--result-file='.$realpath);

        $process = $processBuilder->getProcess();

        mwp_logger()->info('Database dumping process starting', array(
            'executable_location' => $mysqldump,
            'command_line'        => $process->getCommandLine(),
            'table'               => $table,
            'destination'         => $realpath,
        ));

        $process->mustRun();

        mwp_logger()->info('Database dump complete', array(
            'table'       => $table,
            'destination' => $realpath,
            'size'        => @filesize($realpath),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function createStream(array $tables = array())
    {
        if (!mwp_is_shell_available()) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::SHELL_NOT_AVAILABLE, 'Shell not available.');
        }

        $mysqldump      = mwp_container()->getExecutableFinder()->find('mysqldump', 'mysqldump');
        $processBuilder = $this->createProcessBuilder($tables, $mysqldump);

        $process = $processBuilder->getProcess();

        mwp_logger()->info('Database dumping process starting', array(
            'executable_location' => $mysqldump,
            'command_line'        => $process->getCommandLine(),
        ));

        return new MWP_Stream_ProcessOutput($process);
    }

    /**
     * @param array $tables
     * @param       $mysqldump
     *
     * @return Symfony_Process_ProcessBuilder
     */
    protected function createProcessBuilder(array $tables, $mysqldump)
    {
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setTimeout(3600)
            ->setPrefix($mysqldump)
            // Continue even if we get an SQL error.
            ->add('--force')
            // User for login if not current user.
            ->add('--user='.$this->configuration->getUsername())
            // Password to use when connecting to server. If password is not given it's solicited on the tty.
            ->add('--password='.$this->configuration->getPassword())
            // Add a DROP TABLE before each create.
            ->add('--add-drop-table');

        if ($this->options->isSkipLockTables()) {
            // Don't lock all tables for read.
            $processBuilder->add('--lock-tables=false');
        }

        if ($this->options->isSkipExtendedInsert()) {
            $processBuilder->add('--extended-insert=false');
        }

        $processBuilder->add($this->configuration->getDatabase());

        // Dump only specific tables
        foreach ($tables as $table) {
            $processBuilder->add($table);
        }

        if ($this->configuration->isSocket()) {
            $processBuilder->add('--socket='.$this->configuration->getSocketPath());
        } else {
            $processBuilder->add('--host='.$this->configuration->getHost());
            if (($port = $this->configuration->getPort()) !== null) {
                $processBuilder->add('--port='.$port);
            }
        }

        return $processBuilder;
    }
}
