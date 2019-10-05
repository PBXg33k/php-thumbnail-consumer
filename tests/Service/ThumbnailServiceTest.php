<?php
namespace App\Tests\Service;

use App\Service\ThumbnailService;
use org\bovigo\vfs\content\LargeFileContent;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

class ThumbnailServiceTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    private $rootFs;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var string
     */
    private $configPath = __DIR__.'/../../config/mt.json' ;

    /**
     * @var ThumbnailService
     */
    private $service;

    protected function setUp()
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->rootFs = vfsStream::setup('testDir');

        $this->service = new ThumbnailService(
            $this->logger,
            $this->configPath,
            $this->rootFs->url()
        );
    }

    /**
     * @test
     * @expectedException \Exception
     */
    public function willThrowExceptionIfInvalidPathIsGivenToConstructor(): void
    {
        $this->service = new ThumbnailService(
            $this->logger,
            $this->configPath,
            sprintf('/nonexisting/path/%s', md5(random_int(0,999)))
        );
    }

    public function testConfigPathGetter(): void
    {
        $this->assertSame($this->configPath, $this->service->getMtConfigPath());
    }

    public function testSettersGetters(): void
    {
        $configPath = 'blaat';
        $this->assertSame($configPath, $this->service->setMtConfigPath($configPath)->getMtConfigPath());
        $this->assertSame($this->rootFs->url(), $this->service->setThumbnailDirectory($this->rootFs->url())->getThumbnailDirectory());
    }

    /**
     * @test
     */
    public function willRenameThumbnailFromFilenameToInode()
    {
        $inodeId  = 123;

        $filename = 'sintel_trailer-720p';

        // Setup VFS
        vfsStream::newFile("{$filename}.jpg")
            ->withContent(LargeFileContent::withMegabytes(2))
            ->at($this->rootFs);

        $this->logger->expects($this->once())
            ->method('debug');

        $thumbnail = $this->service->getThumbnail("{$this->rootFs->url()}/{$filename}.mp4");

        $this->assertInstanceOf(\SplFileInfo::class, $thumbnail);

        $this->assertFalse($this->rootFs->hasChild("{$filename}.jpg"));
        $this->assertTrue($this->rootFs->hasChild("{$inodeId}.jpg"));
    }

    /**
     * @test
     */
    public function willRemoveAlreadyRenamedThumbnail()
    {
        $inodeId  = 123;

        $filename = 'sintel_trailer-720p';

        // Setup VFS
        vfsStream::newFile("{$filename}.jpg")
            ->withContent(LargeFileContent::withMegabytes(2))
            ->at($this->rootFs);
        vfsStream::newFile("{$inodeId}.jpg")
            ->withContent(LargeFileContent::withMegabytes(2))
            ->at($this->rootFs);

        $this->logger->expects($this->once())
            ->method('debug');

        // Assert before state
        $this->assertTrue($this->rootFs->hasChild("{$filename}.jpg"));
        $this->assertTrue($this->rootFs->hasChild("{$inodeId}.jpg"));

        $thumbnail = $this->service->getThumbnail("{$this->rootFs->url()}/{$filename}.mp4");

        // Assert after state
        $this->assertInstanceOf(\SplFileInfo::class, $thumbnail);
        $this->assertFalse($this->rootFs->hasChild("{$filename}.jpg"));
        $this->assertTrue($this->rootFs->hasChild("{$inodeId}.jpg"));
    }

    /**
     * @test
     */
    public function willNotGenerateAlreadyGeneratedThumbs()
    {
        $inodeId  = 123;

        $filename = 'sintel_trailer-720p';

        // Setup VFS
        vfsStream::newFile("{$filename}.jpg")
            ->withContent(LargeFileContent::withMegabytes(2))
            ->at($this->rootFs);

        $this->logger->expects($this->exactly(2))
            ->method('debug');

        $this->assertFalse($this->service->generateThumbnails("{$this->rootFs->url()}/{$filename}.mp4"));

        $this->assertFalse($this->rootFs->hasChild("{$filename}.jpg"));
        $this->assertTrue($this->rootFs->hasChild("{$inodeId}.jpg"));
    }

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage Path is not a file
     */
    public function willThrowExceptionIfFileDoesNotExistWhenGeneratingThumb()
    {
        $this->logger->expects($this->once())
            ->method('error');

        $this->service->generateThumbs("{$this->rootFs->url()}/sintel_trailer-720p.mp4");
    }

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage File is not readable
     */
    public function willThrowExceptionIfFileIsNotReadableWhenGeneratingThumb()
    {
        $inodeId  = 123;

        $filename = 'sintel_trailer-720p';

        // Setup VFS
        vfsStream::newFile("{$filename}.mp4")
            ->withContent(LargeFileContent::withMegabytes(2))
            ->chmod(0000)
            ->at($this->rootFs);

        $this->logger->expects($this->once())
            ->method('error');

        $this->assertFalse($this->service->generateThumbnails("{$this->rootFs->url()}/{$filename}.mp4"));

        $this->assertFalse($this->rootFs->hasChild("{$filename}.jpg"));
        $this->assertTrue($this->rootFs->hasChild("{$inodeId}.jpg"));
    }

    /**
     * Inject a real video file into vfs for testing mt command
     *
     * @param string $filename
     * @return \org\bovigo\vfs\vfsStreamContent|\org\bovigo\vfs\vfsStreamFile
     */
    private function createVideoTestFile(string $filename)
    {
        return vfsStream::newFile($filename)
            ->withContent(file_get_contents(__DIR__.'/../sintel_trailer-720p.mp4'))
            ->at($this->rootFs);
    }
}