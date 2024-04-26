<?php
namespace App\MessageHandler;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $commentRepository;
    private $entityManager;

    public function __construct(CommentRepository $commentRepository, EntityManagerInterface $entityManager)
    {
        $this->commentRepository = $commentRepository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        $comment->setState('published');
        $this->entityManager->flush();
    }
}
