<?php

namespace App\Controller;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->userRepository->findByEmail($email);
        if ($existing) {
            return $this->json(['error' => 'Un compte existe déjà avec cet email'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(): void
    {
        // Handled by json_login in security.yaml
        throw new \LogicException('This should never be reached.');
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(): void
    {
        // Handled by security.yaml
        throw new \LogicException('This should never be reached.');
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(null, Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/forgot-password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        ResetPasswordHelperInterface $resetPasswordHelper,
        MailerInterface $mailer,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        $user = $this->userRepository->findByEmail($email);

        // Always return success to prevent email enumeration
        if (!$user) {
            return $this->json(['message' => 'Si un compte existe, un email a été envoyé']);
        }

        try {
            $resetToken = $resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json(['message' => 'Si un compte existe, un email a été envoyé']);
        }

        $resetUrl = ($data['resetBaseUrl'] ?? '') . '#/reset-password/' . $resetToken->getToken();

        $emailMessage = (new Email())
            ->from('noreply@plan-de-circulation.local')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->text("Pour réinitialiser votre mot de passe, cliquez sur ce lien :\n\n$resetUrl\n\nCe lien expirera dans 1 heure.");

        $mailer->send($emailMessage);

        return $this->json(['message' => 'Si un compte existe, un email a été envoyé']);
    }

    #[Route('/reset-password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        ResetPasswordHelperInterface $resetPasswordHelper,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (!$token || !$password) {
            return $this->json(['error' => 'Token et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json(['error' => 'Lien de réinitialisation invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }

        $resetPasswordHelper->removeResetRequest($token);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès']);
    }
}
