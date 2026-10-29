<?php

namespace Tygh\Tests\Unit\Functions\Fs;

use Tygh\Tests\Unit\ATestCase;

/**
 * Remote data fetching must go over network transports only.
 *
 * fn_get_contents() and fn_get_url_data() accept a location that may come from user input,
 * so every scheme except http, https, ftp and ftps has to be rejected before anything is read.
 */
class RemoteUrlSchemeTest extends ATestCase
{
    public $runTestInSeparateProcess = true;

    public $backupGlobals = false;

    public $preserveGlobalState = false;

    /**
     * Nothing listens on this port, so a location that passes the scheme check fails at the
     * transport instead of reaching a real host. Keeps the test offline and instant.
     */
    const UNREACHABLE_HOST = '127.0.0.1:1';

    /**
     * Marker stored in a local file that must never be exposed through a stream wrapper.
     */
    const SECRET_CONTENTS = 'TOP_SECRET_CONTENTS';

    /**
     * @var string Path to the local file used as a stream wrapper target
     */
    protected $secret_file;

    protected function setUp(): void
    {
        $this->requireCore('functions/fn.fs.php');

        $this->secret_file = tempnam(sys_get_temp_dir(), 'fn_fs_scheme_');
        file_put_contents($this->secret_file, self::SECRET_CONTENTS);
    }

    protected function tearDown(): void
    {
        if ($this->secret_file && is_file($this->secret_file)) {
            unlink($this->secret_file);
        }
    }

    /**
     * Stream wrapper prefixes that turn a plain local path into a readable location.
     *
     * @return array<string, array{string}>
     */
    public function dpLocalFileWrappers()
    {
        return [
            'file'           => ['file://'],
            'file uppercase' => ['FILE://'],
            'file with host' => ['file://localhost'],
            'php filter'     => ['php://filter/read=convert.base64-encode/resource='],
            'compress zlib'  => ['compress.zlib://'],
            'phar'           => ['phar://'],
            'glob'           => ['glob://'],
            'zip'            => ['zip://'],
        ];
    }

    /**
     * Schemes that read nothing locally, but let the platform talk to a protocol it never should.
     *
     * @return array<string, array{string}>
     */
    public function dpNonTransportSchemes()
    {
        return [
            'data'   => ['data://text/plain,payload'],
            'gopher' => ['gopher://example.com:70/file.txt'],
            'ldap'   => ['ldap://127.0.0.1:389/file.txt'],
            'dict'   => ['dict://127.0.0.1:11211/file.txt'],
            'expect' => ['expect://id'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public function dpAllowedSchemes()
    {
        return [
            'http'             => ['http'],
            'https'            => ['https'],
            'ftp'              => ['ftp'],
            'ftps'             => ['ftps'],
            'http uppercase'   => ['HTTP'],
            'https mixed case' => ['HttpS'],
        ];
    }

    /**
     * Allowed scheme, but no host to fetch from: the only thing left to open is a local path.
     *
     * @return array<string, array{string}>
     */
    public function dpUrlsWithoutHost()
    {
        return [
            'http'  => ['http:///tmp/secret.txt'],
            'https' => ['https:///tmp/secret.txt'],
            'ftp'   => ['ftp:///tmp/secret.txt'],
        ];
    }

    /**
     * A rejected location yields an empty string: nothing was read and nothing was fetched.
     *
     * @param string $prefix Stream wrapper prefix prepended to a real local file path
     *
     * @dataProvider dpLocalFileWrappers
     */
    public function testGetContentsRejectsLocalFileWrappers($prefix)
    {
        $this->assertSame('', fn_get_contents($prefix . $this->secret_file));
    }

    /**
     * @param string $url URL to fetch
     *
     * @dataProvider dpNonTransportSchemes
     */
    public function testGetContentsRejectsNonTransportSchemes($url)
    {
        $this->assertSame('', fn_get_contents($url));
    }

    /**
     * @param string $url URL to fetch
     *
     * @dataProvider dpUrlsWithoutHost
     */
    public function testGetContentsRejectsUrlWithoutHost($url)
    {
        $this->assertSame('', fn_get_contents($url));
    }

    /**
     * The allow list must not be narrower than the transports the platform relies on.
     * A rejected location returns an empty string, so any other result means the location
     * passed the scheme check and the fetch was actually attempted.
     *
     * @param string $scheme URL scheme to fetch with
     *
     * @dataProvider dpAllowedSchemes
     */
    public function testGetContentsAllowsNetworkTransportSchemes($scheme)
    {
        $url = $scheme . '://' . self::UNREACHABLE_HOST . '/file.txt';

        $this->assertNotSame('', fn_get_contents($url));
    }

    public function testGetContentsReadsPlainLocalPath()
    {
        // A path without a scheme is still a legitimate local read.
        $this->assertSame(self::SECRET_CONTENTS, fn_get_contents($this->secret_file));
    }

    /**
     * @param string $prefix Stream wrapper prefix prepended to a real local file path
     *
     * @dataProvider dpLocalFileWrappers
     */
    public function testGetUrlDataRejectsLocalFileWrappers($prefix)
    {
        $this->assertFalse(fn_get_url_data($prefix . $this->secret_file));
    }

    /**
     * Padding a wrapper with whitespace must not smuggle it past the scheme check.
     */
    public function testWhitespacePaddedWrapperIsRejected()
    {
        $location = " \n\tfile://" . $this->secret_file . " \n\t";

        $this->assertSame('', fn_get_contents($location));
        $this->assertFalse(fn_get_url_data($location));
    }
}
