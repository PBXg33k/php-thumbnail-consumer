<?php

namespace App\MessageHandler;


use App\Service\ThumbnailService;
use Pbxg33k\MessagePack\Message\GenerateThumbnailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class GenerateThumbnailMessageHandler
{
    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ThumbnailService $thumbnailService,
        LoggerInterface $logger
    )
    {
        $this->thumbnailService = $thumbnailService;
        $this->logger = $logger;
    }

    public function __invoke(GenerateThumbnailMessage $message)
    {
        try {
            $result = $this->thumbnailService->generateThumbnails($message->getPath());

            if($result === true) {
                $this->logger->info('Success');

                $this->thumbnailService->renameFilenameToInode(
                    $this->thumbnailService->getThumbPath($message->getPath())
                );

                return true;
            } else {
                $this->logger->error('Error');
            }
        } catch (ProcessFailedException $exception) {
            throw new UnrecoverableMessageHandlingException('Process failed: '. $exception->getMessage());
        } catch (ProcessTimedOutException $exception) {
            throw new UnrecoverableMessageHandlingException('Process timeout: '. $exception->getMessage());
        } catch (\Throwable $exception) {
            throw new UnrecoverableMessageHandlingException('Unknown error: '. $exception->getMessage());
        }

        return false;
    }
}