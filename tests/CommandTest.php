<?php

namespace nedarta\CssMin\Tests;

use nedarta\CssMin\Command;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    protected $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__);
    }

    protected function invokeProtected($method, $arguments)
    {
        $ref = new \ReflectionMethod(Command::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(new Command, $arguments);
    }

    /**
     * Runs the cssmin bin and returns array(stdout, stderr, exit code)
     */
    protected function execBin($arguments)
    {
        $cmd = escapeshellarg(PHP_BINARY) .' '. escapeshellarg($this->projectRoot .'/cssmin');
        foreach ($arguments as $argument) {
            $cmd .= ' '. escapeshellarg($argument);
        }

        $pipes = array();
        $spec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc = proc_open($cmd, $spec, $pipes);
        $this->assertIsResource($proc);

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($proc);
        proc_close($proc);

        return array($stdout, $stderr, $status['exitcode']);
    }

    public function testGetOptReturnsNullWhenOptionIsMissing()
    {
        $this->assertNull($this->invokeProtected('getOpt', array('i', array())));
        $this->assertNull($this->invokeProtected('getOpt', array(array('i', 'input'), array())));
    }

    public function testGetOptReturnsFirstMatchingAlias()
    {
        $options = array('input' => 'f.css');

        $this->assertSame('f.css', $this->invokeProtected('getOpt', array(array('i', 'input'), $options)));
        $this->assertNull($this->invokeProtected('getOpt', array(array('i', 'input'), array('x' => 'f.css'))));
    }

    public function testFormatBytes()
    {
        $this->assertSame('1023 B', $this->invokeProtected('formatBytes', array(1023)));
        $this->assertSame('1 K', $this->invokeProtected('formatBytes', array(1024)));
        $this->assertSame('1 M', $this->invokeProtected('formatBytes', array(1048576)));
        $this->assertSame('1.5 K', $this->invokeProtected('formatBytes', array(1536)));
    }

    public function testFormatMicroSeconds()
    {
        $this->assertSame('50 ms', $this->invokeProtected('formatMicroSeconds', array(0.05)));
        $this->assertSame('188.12 ms', $this->invokeProtected('formatMicroSeconds', array(0.1881234)));
        $this->assertSame('1.5 s', $this->invokeProtected('formatMicroSeconds', array(1.5)));
        $this->assertSame('2 m', $this->invokeProtected('formatMicroSeconds', array(120)));
        $this->assertSame('0 ms', $this->invokeProtected('formatMicroSeconds', array(0)));
    }

    public function testHelpFlag()
    {
        list($stdout, $stderr, $exitCode) = $this->execBin(array('--help'));
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Usage: cssmin', $stdout);
    }

    public function testMissingInputArgument()
    {
        list($stdout, $stderr, $exitCode) = $this->execBin(array());
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('-i <file> argument is missing', $stderr);
    }

    public function testUnreadableInputFile()
    {
        $missing = $this->projectRoot .'/nonexistent-file.css';
        list($stdout, $stderr, $exitCode) = $this->execBin(array('-i', $missing));
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Input file "'. $missing .'" is not readable', $stderr);
    }

    public function testUnreadableInputFileAmongSeveralIsReported()
    {
        $missing = $this->projectRoot .'/nonexistent-file.css';
        list($stdout, $stderr, $exitCode) = $this->execBin(
            array('-i', $this->tempFile('cssmin-merge-ok.css', '.a{color:red}'),
                  '-i', $missing)
        );
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Input file "'. $missing .'" is not readable', $stderr);
    }

    public function testMergesMultipleInputFiles()
    {
        $first = $this->tempFile('cssmin-merge-1.css', '@charset "UTF-8";.a { color: white; }');
        $second = $this->tempFile('cssmin-merge-2.css', '@charset "ISO-8859-1";.b { margin: 0px; }');
        $output = $this->tempFile('cssmin-merge-dst.css', '');

        list($stdout, $stderr, $exitCode) = $this->execBin(array('-i', $first, '-i', $second, '-o', $output));

        $this->assertSame(0, $exitCode);
        $this->assertSame('@charset "UTF-8";.a{color:#fff}.b{margin:0}', file_get_contents($output));
    }

    public function testMergesMultipleInputFilesUsingLongAlias()
    {
        $first = $this->tempFile('cssmin-merge-3.css', '.a { color: white; }');
        $second = $this->tempFile('cssmin-merge-4.css', '.b { margin: 0px; }');
        $output = $this->tempFile('cssmin-merge-dst-2.css', '');

        list($stdout, $stderr, $exitCode) = $this->execBin(
            array('--input', $first, '--input', $second, '--output', $output)
        );

        $this->assertSame(0, $exitCode);
        $this->assertSame('.a{color:#fff}.b{margin:0}', file_get_contents($output));
    }

    public function testDryRun()
    {
        $input = $this->tempFile('cssmin-dry-run-src.css', '.a { color: white; margin: 0px; }');

        list($stdout, $stderr, $exitCode) = $this->execBin(array('--dry-run', '-i', $input));

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CSSMIN STATS', $stdout);
        $this->assertStringContainsString('Space savings:', $stdout);
        $this->assertSame('', $stderr);
    }

    public function testCompressedOutputWrittenToFile()
    {
        $input = $this->tempFile('cssmin-out-src.css', '#a { color: white; margin: 0px; }');
        $output = $this->tempFile('cssmin-out-dst.css', '');

        list($stdout, $stderr, $exitCode) = $this->execBin(array('-i', $input, '-o', $output));

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CSSMIN STATS', $stdout);
        $this->assertSame('#a{color:#fff;margin:0}', file_get_contents($output));
    }

    public function testLinebreakPositionOption()
    {
        $input = $this->tempFile('cssmin-lbp-src.css', '#a{color:#fff}#b{color:#000}#c{color:#999}');
        $output = $this->tempFile('cssmin-lbp-dst.css', '');

        list($stdout, $stderr, $exitCode) = $this->execBin(array('-i', $input, '-o', $output, '--linebreak-position', '10'));

        $this->assertSame(0, $exitCode);
        $lines = file($output);
        $this->assertGreaterThan(1, count($lines));
    }

    public function testResolveImportsFlagInlinesReferencedStylesheets()
    {
        $dir = tempnam(sys_get_temp_dir(), 'cssmin-res');
        @unlink($dir);
        mkdir($dir);
        $main = $dir . DIRECTORY_SEPARATOR .'main.css';
        $partial = $dir . DIRECTORY_SEPARATOR .'partials.css';

        file_put_contents($main, "@import url(partials.css);\n.main { color: white; }");
        file_put_contents($partial, ".partial { margin: 0px; }");
        $output = $this->tempFile('cssmin-res-dst.css', '');

        list($stdout, $stderr, $exitCode) = $this->execBin(array('--resolve-imports', '-i', $main, '-o', $output));

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CSSMIN STATS', $stdout);
        $this->assertSame('.partial{margin:0}.main{color:#fff}', file_get_contents($output));

        unlink($main);
        unlink($partial);
        rmdir($dir);
    }

    protected function tempFile($name, $content)
    {
        $path = tempnam(sys_get_temp_dir(), str_replace('#', '', $name)) .'.css';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    protected $tempFiles = array();

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
