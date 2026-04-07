<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Model\BadRequest;
use App\Service\ResponseMediaTypeResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

final class BadRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private ResponseMediaTypeResolver $responseMediaTypeResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof BadRequest) {
            return;
        }

        $mediaType = $this->responseMediaTypeResolver->resolve($event->getRequest());
        $format = 'application/xml' === $mediaType ? 'xml' : 'json';
        $content = $this->serializer->serialize(
            ['message' => $throwable->getMessage()],
            $format,
        );

        $event->setResponse(
            new Response(
                $content,
                Response::HTTP_BAD_REQUEST,
                ['Content-Type' => $mediaType],
            ),
        );
    }

}
