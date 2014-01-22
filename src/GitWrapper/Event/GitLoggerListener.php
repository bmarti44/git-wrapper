<?php

namespace GitWrapper\Event;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GitLoggerListener implements EventSubscriberInterface, LoggerAwareInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * {@inheritDoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            GitEvents::GIT_PREPARE => array('onPrepare', 0),
            GitEvents::GIT_OUTPUT  => array('handleOutput', 0),
            GitEvents::GIT_SUCCESS => array('onSuccess', 0),
            GitEvents::GIT_ERROR   => array('onError', 0),
            GitEvents::GIT_BYPASS  => array('onBypass', 0),
        );
    }

    public function onPrepare(GitEvent $event)
    {
        $this->logger->info('Git command preparing to run.', array(
            'command' => $event->getProcess()->getCommandLine()
        ));
    }

    public function handleOutput(GitOutputEvent $event)
    {
        $this->logger->debug($event->getBuffer(), array(
            'command' => $event->getProcess()->getCommandLine(),
            'error' => $event->isError() ? true : false,
        ));
    }

    public function onSuccess(GitEvent $event)
    {
        $this->logger->info('Git command successfully run.', array(
            'command' => $event->getProcess()->getCommandLine()
        ));
    }

    public function onError(GitEvent $event)
    {
        $this->logger->error('Error running Git command.', array(
            'command' => $event->getProcess()->getCommandLine()
        ));
    }

    public function onBypass(GitEvent $event)
    {
        $this->logger->info('Git command bypassed.', array(
            'command' => $event->getProcess()->getCommandLine()
        ));
    }
}
