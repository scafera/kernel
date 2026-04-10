<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http\Internal;

use Scafera\Kernel\Http\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal Converts a Scafera Response returned by a controller into a Symfony Response.
 */
final class ResponseListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::VIEW => ['onKernelView', 0]];
    }

    public function onKernelView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if ($result instanceof Response) {
            $event->setResponse(ResponseConverter::toSymfony($result));
        }
    }
}
