<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PhpProcess runs a PHP script in an independent process.
 *
 * $p = new PhpProcess('<?php echo "foo"; ?>');
 * $p->run();
 * print $p->getOutput()."\n";
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Symfony_Process_PhpProcess extends Symfony_Process_Process
{
    /**
     * Constructor.
     *
     * @param string $script  The PHP script to run (as a string)
     * @param string $cwd     The working directory
     * @param array  $env     The environment variables
     * @param int    $timeout The timeout in seconds
     * @param array  $options An array of options for proc_open
     *
     * @api
     */
    public function __construct($script, $cwd = null, array $env = array(), $timeout = 60, array $options = array())
    {
        $executableFinder = new Symfony_Process_PhpExecutableFinder();
        if (false === $php = $executableFinder->find()) {
            $php = null;
        }

        parent::__construct($php, $cwd, $env, $script, $timeout, $options);
    }

    /**
     * Sets the path to the PHP binary to use.
     *
     * @api
     */
    public function setPhpBinary($php)
    {
        $this->setCommandLine($php);
    }

    /**
     * {@inheritdoc}
     */
    public function start($callback = null)
    {
        if (null === $this->getCommandLine()) {
            throw new Symfony_Process_Exception_RuntimeException('Unable to find the PHP executable.');
        }

        parent::start($callback);
    }
}
