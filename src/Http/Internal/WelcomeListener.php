<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http\Internal;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\NoConfigurationException;

/**
 * @internal Replaces Symfony's default welcome page with a Scafera message.
 */
final class WelcomeListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $welcome,
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', -32]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->debug) {
            return;
        }

        $e = $event->getThrowable();

        if (!$e instanceof NotFoundHttpException || !$e->getPrevious() instanceof NoConfigurationException) {
            return;
        }

        $event->setResponse(new Response($this->welcome, Response::HTTP_NOT_FOUND));
    }
}
