<?php
namespace App\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ActiveAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokens,private UrlGeneratorInterface $urls) {}
    public static function getSubscribedEvents(): array
    {
        return ['kernel.request'=>['check',0]];
    }
    public function check(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        $user=$this->tokens->getToken()?->getUser();
        if ($user instanceof User && !$user->getIsActive()) {
            $this->tokens->setToken(null);
            if ($event->getRequest()->hasSession()) $event->getRequest()->getSession()->invalidate();
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
        }
    }
}
