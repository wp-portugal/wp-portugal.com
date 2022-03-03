<?php

use PHPUnit\Framework\TestCase;
use LkWdwrd\MuPluginLoader\Util;

class UtilTest extends TestCase
{
    /**
     * Include the necessary files.
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Make sure the rel_path function makes relative paths.
     * @dataProvider data_rel_path
     */
    public function test_rel_path($path1, $path2, $sep, $expected): void
    {
        self::assertEquals($expected, Util\rel_path($path1, $path2, $sep));
    }

    public function data_rel_path(): array
    {
        return [
            [
                '/a/random/path/to/a/place',
                '/a/random/path/to/another/place',
                '/',
                '../../another/place',
            ],
            [
                '/somewhere/over/the/rainbow',
                '/somewhere/over/the/rainbow/bluebirds/sing',
                '/',
                'bluebirds/sing',
            ],
            [
                '/just/some/test',
                '/just\some\mixed\slash\example',
                '/',
                '../mixed/slash/example',
            ],
            [
                '/testing/inverse/directory/separators',
                '/unix/to/windows',
                '\\',
                '..\..\..\..\unix\to\windows',
            ],
        ];
    }
}
