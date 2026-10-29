<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

namespace Tygh\Tests\Unit\ElFinder;

use Tygh\ElFinder\Core;
use Tygh\ElFinder\Volume;
use Tygh\Tests\Unit\ATestCase;

class VolumeTest extends ATestCase
{
    /** @var string */
    protected $outside;

    /** @var string */
    protected $root;

    /** @var Core */
    protected $core;

    /** @var VolumeStub */
    protected $volume;

    /** @var string */
    protected $root_hash;

    /** @var string|null */
    protected $original_request_method;

    protected function setUp(): void
    {
        // The volume driver reads the request method when it is mounted
        $this->original_request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        if (!defined('CHARSET')) {
            define('CHARSET', 'utf-8');
        }

        // Required before the temp dir is created so fn_rm() is available to tearDown even if setUp fails early.
        $this->requireCore('functions/fn.common.php');
        $this->requireCore('functions/fn.fs.php');
        $this->requireCore('lib/other/elfinder/elFinderSessionInterface.php');

        $this->outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'elfinder_volume_test_' . uniqid();
        $this->root = $this->outside . DIRECTORY_SEPARATOR . 'root';
        mkdir($this->root, 0777, true);
        touch($this->root . DIRECTORY_SEPARATOR . 'existing.txt');

        $this->core = new Core([
            'session'        => $this->createSession(),
            'commonTempPath' => $this->outside,
            'roots'          => [
                [
                    'driver'     => VolumeStub::class,
                    'path'       => $this->root,
                    'mimeDetect' => 'internal',
                ],
            ],
        ]);

        $opened = $this->core->exec('open', ['target' => '', 'init' => true, 'tree' => false]);
        $this->assertArrayNotHasKey('error', $opened);

        $this->root_hash = $opened['cwd']['hash'];
        $this->volume = $this->core->getVolume($this->root_hash);
    }

    protected function tearDown(): void
    {
        if ($this->outside !== null && is_dir($this->outside)) {
            fn_rm($this->outside);
        }

        if ($this->original_request_method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->original_request_method;
        }
    }

    /**
     * @dataProvider getJoinPathTestData
     */
    public function testJoinPathKeepsResultInsideDirectory($name, $expected_name)
    {
        $this->assertEquals($this->root . DIRECTORY_SEPARATOR . $expected_name, $this->volume->joinPath($this->root, $name));
    }

    public function getJoinPathTestData()
    {
        return [
            'plain name'                  => ['index.html', 'index.html'],
            'nested path without dot-dot' => ['sub/index.html', 'sub/index.html'],
            'leading dot-dot'             => ['../index.html', 'index.html'],
            'absolute traversal'          => ['/../../../index.html', 'index.html'],
            'traversal in the middle'     => ['sub/../../index.html', 'index.html'],
            'windows separators'          => ['..\\..\\index.html', 'index.html'],
            'only dot-dot'                => ['..', ''],
        ];
    }

    /**
     * @dataProvider getRejectedNamesTestData
     */
    public function testFileIsNotCreatedOutsideRoot($name)
    {
        // A real subdirectory so that, if the separator guard ever regressed, a name like
        // "sub/index.html" would actually create a file instead of merely failing with ENOENT.
        mkdir($this->root . DIRECTORY_SEPARATOR . 'sub');

        $before = $this->treeSnapshot($this->outside);

        $created = $this->core->exec('mkfile', ['target' => $this->root_hash, 'name' => $name]);
        $this->assertArrayHasKey('error', $created);

        $renamed = $this->core->exec('rename', ['target' => $this->volume->getHash($this->root, 'existing.txt'), 'name' => $name]);
        $this->assertArrayHasKey('error', $renamed);

        // The original file survived and neither operation created anything anywhere under the
        // parent directory — this catches an escape to any location, not just a single hard-coded one.
        $this->assertFileExists($this->root . DIRECTORY_SEPARATOR . 'existing.txt');
        $this->assertEquals($before, $this->treeSnapshot($this->outside));
    }

    public function getRejectedNamesTestData()
    {
        return [
            'leading dot-dot'         => ['../index.html'],
            'traversal to parent'     => ['/../index.html'],
            'traversal in the middle' => ['sub/../../index.html'],
            'deep traversal'          => ['/../../../index.html'],
            'nested path'             => ['sub/index.html'],
            'windows separators'      => ['..\\..\\index.html'],
            'leading dot'             => ['.htaccess'],
            'nul byte'                => ["index\0.html"],
        ];
    }

    public function testFileIsCreatedAndRenamedInsideRoot()
    {
        $created = $this->core->exec('mkfile', ['target' => $this->root_hash, 'name' => 'index.html']);
        $this->assertArrayHasKey('added', $created);
        $this->assertFileExists($this->root . DIRECTORY_SEPARATOR . 'index.html');

        $renamed = $this->core->exec('rename', ['target' => $this->volume->getHash($this->root, 'existing.txt'), 'name' => 'renamed.txt']);
        $this->assertArrayHasKey('added', $renamed);
        $this->assertFileExists($this->root . DIRECTORY_SEPARATOR . 'renamed.txt');
        $this->assertFileNotExists($this->root . DIRECTORY_SEPARATOR . 'existing.txt');
    }

    /**
     * @return \elFinderSessionInterface
     */
    protected function createSession()
    {
        return new class implements \elFinderSessionInterface {
            /** @var array */
            protected $data = [];

            public function start()
            {
                return $this;
            }

            public function close()
            {
                return $this;
            }

            public function get($key, $empty = '')
            {
                return isset($this->data[$key]) ? $this->data[$key] : $empty;
            }

            public function set($key, $data)
            {
                $this->data[$key] = $data;

                return $this;
            }

            public function remove($key)
            {
                unset($this->data[$key]);

                return $this;
            }
        };
    }

    /**
     * @param string $dir
     *
     * @return string[] Sorted list of every file and directory under $dir (recursive).
     */
    protected function treeSnapshot($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $paths = [];
        foreach ($iterator as $path => $info) {
            $paths[] = $path;
        }
        sort($paths);

        return $paths;
    }
}

class VolumeStub extends Volume
{
    public function joinPath($dir, $name)
    {
        return $this->_joinPath($dir, $name);
    }
}
