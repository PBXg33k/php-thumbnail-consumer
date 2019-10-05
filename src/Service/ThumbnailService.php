<?php

namespace App\Service;

use mysql_xdevapi\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ThumbnailService
{
    /**
     * @var string
     */
    private $mtConfigPath;

    /**
     * @var string
     */
    private $thumbnailDirectory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ThumbnailService constructor.
     * @param LoggerInterface $logger
     * @param string $mtConfigPath
     * @param string $thumbnailDirectory
     * @throws \Exception
     */
    public function __construct(
        LoggerInterface $logger,
        string $mtConfigPath,
        string $thumbnailDirectory
    )
    {
        $this->logger = $logger;
        $this->mtConfigPath = $mtConfigPath;
        $this->setThumbnailDirectory($thumbnailDirectory);
    }

    /**
     * @param string $sourcePath
     * @return bool
     * @throws \Exception
     */
    public function generateThumbnails(string $sourcePath)
    {
        $this->logger->debug('Generating thumbnails', [
            'path' => $sourcePath
        ]);

        $targetPath = $this->getThumbPath($sourcePath);

        $finfo = $this->getFile($sourcePath);
        if(!$finfo->isFile()) {
            $this->logger->error('Not a file', [
                'path' => $sourcePath
            ]);
            throw new \Exception('Not a file');
        }

        if(!$finfo->isReadable()) {
            $this->logger->error('File is not readable', [
                'path' => $sourcePath,
            ]);
            throw new \Exception('File is not readable');
        }

        $process = new Process([
            'test',
            '-r',
            "\"{$sourcePath}\""
        ]);

        if($process->getExitCode()) {
            $this->logger->error('File not readable by forked process',[
                'path' => $sourcePath
            ]);

            return false;
        }

        $process = (new Process([
            'mt',
            '--config-file',
            $this->getMtConfigPath(),
            '--output',
            $targetPath,
            $sourcePath,
        ]))->setTimeout(10 * 60);
        $this->logger->debug('Running MT CMD', [
            'cmd' => $process->getCommandLine(),
        ]);

        $pid = null;
        try {
            $process->start(function ($type, $buffer) {
                if (preg_match('~(?<level>[^\[]+)\[(\d+)\]\s(?<message>.*)~', $buffer, $matches)) {
                    switch ($matches['level']) {
                        case 'DEBU':
                            $loglevel = 'debug';
                            break;
                        case 'INFO':
                            $loglevel = 'info';
                            break;
                        default:
                            $loglevel = 'error';
                    }

                    $this->logger->log($loglevel, $matches['level'], [
                        'process' => [
                            'type' => $type,
                            'buffer' => $buffer,
                        ],
                    ]);
                } else {
                    $this->logger->debug('CMD OUTPUT', [
                        'buffer' => $buffer,
                    ]);
                }
            });

            $pid = $process->getPid();
            $process->wait();

            return 0 === $process->getExitCode();
        } catch (ProcessFailedException $exception) {
            $this->logger->error($exception->getMessage(), [
                'cmd' => $process->getCommandLine(),
                'file' => $sourcePath,
                'exception_code' => $exception->getCode(),
                'process_exitcode' => $exception->getProcess()->getExitCode(),
                'process_output' => $exception->getProcess()->getOutput(),
                'proc' => [
                    'isTty' => $process->isTty(),
                    'isPty' => $process->isPty(),
                    'working_dir' => $process->getWorkingDirectory(),
                    'env' => $process->getEnv(),
                ],
            ]);

            throw $exception;
        } catch (ProcessTimedOutException $exception) {
            $this->logger->error($exception->getMessage(), [
                'cmd' => $process->getCommandLine(),
                'file' => $sourcePath,
                'exception_code' => $exception->getCode(),
                'process_exitcode' => $exception->getProcess()->getExitCode(),
                'process_output' => $exception->getProcess()->getOutput(),
                'proc' => [
                    'isTty' => $process->isTty(),
                    'isPty' => $process->isPty(),
                    'working_dir' => $process->getWorkingDirectory(),
                    'env' => $process->getEnv(),
                ],
            ]);

            if ($pid !== null && posix_getpgid($pid)) {
                if (!posix_kill($pid, SIGTERM) || !posix_kill($pid, SIGKILL)) {
                    throw $exception;
                }
            }
        }

        return false;
    }

    public function renameFilenameToInode(string $path)
    {
        $newPath = $this->getInodePath($path);

        if(!is_file($newPath)) {
            if(!rename($path, $newPath)) {
                $this->logger->error('Failed to rename file', [
                    'oldPath' => $path,
                    'newPath' => $newPath
                ]);
                throw new Exception('Failed to rename file');
            }
            $this->logger->debug('Renamed file', [
                'oldPath' => $path,
                'newPath' => $newPath
            ]);
        } else {
            $this->logger->notice('Removing old file', [
                'path' => $path
            ]);
            unlink($path);
        }
    }

    public function getThumbnail(string $path) :? \SplFileInfo
    {
        return $this->getFile($path);
    }

    private function getFile(string $path) :? \SplFileInfo
    {
        if(file_exists($path)) {
            return new \SplFileInfo($path);
        }
    }

    /**
     * @param string $path
     * @return int
     * @throws \Exception if $path is not a file
     */
    private function getInodeByPath(string $path): int
    {
        if(is_file($path)) {
            return fileinode($path);
        }

        throw new \Exception('Invalid Path');
    }

    public function getThumbPath(string $path): string
    {
        $pathInfo = pathinfo($path);

        return "{$this->thumbnailDirectory}/{$pathInfo['filename']}.jpg";
    }

    private function getInodePath(string $path): string
    {
        return "{$this->thumbnailDirectory}/{$this->getInodeByPath($path)}.jpg";
    }

    /**
     * @return string
     */
    public function getMtConfigPath(): string
    {
        return $this->mtConfigPath;
    }

    /**
     * @param string $mtConfigPath
     * @return ThumbnailService
     */
    public function setMtConfigPath(string $mtConfigPath): ThumbnailService
    {
        $this->mtConfigPath = $mtConfigPath;
        return $this;
    }

    /**
     * @return string
     */
    public function getThumbnailDirectory(): string
    {
        return $this->thumbnailDirectory;
    }

    /**
     * @param string $thumbnailDirectory
     * @return ThumbnailService
     * @throws \Exception
     */
    public function setThumbnailDirectory(string $thumbnailDirectory): ThumbnailService
    {
        if(!is_dir($thumbnailDirectory)) {
            throw new \Exception('Not a valid directory');
        }

        $this->thumbnailDirectory = $thumbnailDirectory;
        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return ThumbnailService
     */
    public function setLogger(LoggerInterface $logger): ThumbnailService
    {
        $this->logger = $logger;
        return $this;
    }
}