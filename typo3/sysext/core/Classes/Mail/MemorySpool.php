<?php
declare(strict_types=1);
namespace TYPO3\CMS\Core\Mail;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Because TYPO3 doesn't offer a terminate signal or hook,
 * and taking in account the risk that extensions do some redirects or even exit,
 * we simply use the destructor of a singleton class which should be pretty much
 * at the end of a request.
 *
 * To have only one memory spool per request seems to be more appropriate anyway.
 *
 * @internal This class is experimental and subject to change!
 */
class MemorySpool extends AbstractTransport implements SingletonInterface, LoggerAwareInterface, DelayedTransportInterface
{
    use LoggerAwareTrait;

    /**
     * @var SentMessage[]
     */
    protected $queuedMessages;

    /**
     * Maximum number of retries when the real transport has failed.
     *
     * @var int
     */
    protected $retries = 3;

    /**
     * Sends out the messages in the memory
     */
    public function __destruct()
    {
        $mailer = GeneralUtility::makeInstance(Mailer::class);
        try {
            $this->flushQueue($mailer->getRealTransport());
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('An Exception occurred while flushing email queue: ' . $exception->getMessage());
        }
    }

    /**
     * @inheritdoc
     */
    public function flushQueue(TransportInterface $transport): int
    {
        if (!$this->queuedMessages) {
            return 0;
        }

        $retries = $this->retries;
        $message = null;
        $count = 0;
        while ($retries--) {
            try {
                while ($message = array_pop($this->queuedMessages)) {
                    $transport->send($message->getMessage(), $message->getEnvelope());
                    $count++;
                }
            } catch (TransportExceptionInterface $exception) {
                if ($retries && $message) {
                    // re-queue the message at the end of the queue to give a chance
                    // to the other messages to be sent, in case the failure was due to
                    // this message and not just the transport failing
                    array_unshift($this->queuedMessages, $message);

                    // wait half a second before we try again
                    usleep(500000);
                } else {
                    throw $exception;
                }
            }
        }
        return $count;
    }

    /**
     * Stores a message in the queue.
     * @param SentMessage $message
     */
    protected function doSend(SentMessage $message): void
    {
        $this->queuedMessages[] = $message;
    }

    public function __toString(): string
    {
        $result = '';
        foreach ($this->queuedMessages as $sentMessage) {
            $result .= $sentMessage->toString() . "\n";
        }
        return $result;
    }
}
