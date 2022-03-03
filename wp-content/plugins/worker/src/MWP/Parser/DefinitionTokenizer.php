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
 * A simple parser able to extract 'define' calls with their first constant string, from a PHP source code.
 * Helpful for configuration reading.
 */
class MWP_Parser_DefinitionTokenizer
{
    /**
     * Returns an array of possible 'defined' values.
     *
     * @param string $content Content to parse, PHP code is expected.
     *
     * @return array All the definitions that could be found.
     */
    public function getDefinitions($content)
    {
        $tokens = token_get_all($content);

        $definitions = array();

        // The parser has 4 phases:
        // 1 - found the 'define' keyword
        // 2 - found an open parentheses '('
        // 3 - found a constant string
        // 0 - (closing the circle) found a comma ','
        $phase          = 0;
        $lastDefinition = '';

        foreach ($tokens as $token) {
            if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                // Skip whitespace and comment tokens.
                continue;
            }

            if ($phase === 0) {
                // Look for a 'define' function call.
                if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'define') {
                    // This is a 'define' call, move to next phase.
                    $phase = 1;
                }
            } elseif ($phase === 1 && $token === '(') {
                // Open parentheses found, move to next phase.
                $phase = 2;
            } elseif ($phase === 2 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                // Constant string found, save it for later
                $lastDefinition = substr($token[1], 1, -1);
                $phase          = 3;
            } elseif ($phase === 3 && $token === ',') {
                // Comma found, save the last found constant string, and reset the parser.
                $definitions[] = $lastDefinition;
                $phase         = 0;
            } else {
                // Unsupported token found, reset the parser phase.
                $phase = 0;
            }
        }

        return array_unique($definitions);
    }

    public function getDefinitionValues($content)
    {
        $tokens = token_get_all($content);

        $definitions = array();

        $phase          = 0;
        $lastDefinition = '';
        $lastValue      = '';

        foreach ($tokens as $token) {
            if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                // Skip whitespace and comment tokens.
                continue;
            }
            if ($phase === 0) {
                // Look for a 'define' function call.
                if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'define') {
                    // This is a 'define' call, move to next phase.
                    $phase = 1;
                }
            } elseif ($phase === 1 && $token === '(') {
                // Open parentheses found, move to next phase.
                $phase = 2;
            } elseif ($phase === 2 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                // Constant string found, save it for later
                $lastDefinition = trim($token[1], '"\'');
                $phase          = 3;
            } elseif ($phase === 3 && $token === ',') {
                // Comma found, save the last found constant string.
                $phase = 4;
            } elseif ($phase === 4 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                if (strlen($token[1]) === 2) {
                    $lastValue = '';
                } else {
                    $lastValue = stripslashes(substr($token[1], 1, -1));
                }
                $phase = 5;
            } elseif ($phase === 5 && $token === ')') {
                $definitions[$lastDefinition] = $lastValue;
                $phase                        = 0;
            } else {
                // Unsupported token found, reset the parser phase.
                $phase = 0;
            }
        }

        return $definitions;
    }
}
