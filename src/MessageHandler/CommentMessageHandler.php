<?php
namespace App\MessageHandler;
use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
class CommentMessageHandler implements MessageHandlerInterface
{
    private $commentRepository;
    private $entityManager;
    private $bus;
    private $workflow;
    private $mailer;
    private $imageOptimizer;
    private $adminEmail;
    private $photoDir;
    private $logger;

    public function __construct(CommentRepository $commentRepository, EntityManagerInterface $entityManager, MessageBusInterface $bus, WorkflowInterface $commentStateMachine, MailerInterface $mailer, string $adminEmail, LoggerInterface $logger, string $photoDir, ImageOptimizer $imageOptimizer)
    {
        $this->commentRepository = $commentRepository;
        $this->entityManager = $entityManager;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->mailer = $mailer;
        $this->adminEmail = $adminEmail;
        $this->logger = $logger;
        $this->photoDir = $photoDir;
        $this->imageOptimizer = $imageOptimizer;
    }
    


    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }
        if ($this->workflow->can($comment, 'accept')) {
                        $transition = 'accept';
                        $this->workflow->apply($comment, $transition);
                        $this->entityManager->flush();
            
                        $this->bus->dispatch($message);
                    } elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')) {
                        $this->mailer->send((new NotificationEmail())
                                        ->subject('New comment posted')
                                        ->htmlTemplate('emails/comment_notification.html.twig')
                                        ->from($this->adminEmail)
                                        ->to($this->adminEmail)
                                        ->context(['comment' => $comment])
                                    );
                                } elseif ($this->workflow->can($comment, 'optimize')) {
                                    if ($comment->getPhotoFilename()) {
                                        $this->imageOptimizer->resize($this->photoDir.'/'.$comment->getPhotoFilename());
                                    }
                                    $this->workflow->apply($comment, 'optimize');
                                    $this->entityManager->flush();
                                

                    } elseif ($this->logger) {
                        $this->logger->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
                    }
    }
}
