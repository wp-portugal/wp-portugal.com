<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Debug_FatalErrorException extends Exception
{
    public function setLine($line)
    {
        $this->line = $line;
    }

    public function setFile($file)
    {
        $this->file = $file;
    }
}
