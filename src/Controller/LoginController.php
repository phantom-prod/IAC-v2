<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ResetToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class LoginController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }
    /**
     * @Route("/reset", name="reset_password")
     */
    public function resetPassword(Request $request, MailerInterface $mailer):Response
    {
        $email = $request->query->get('email');
        if($email === NULL)
        {
            return $this->render('reset.html.twig');
        }
        $repository = $this->getDoctrine()->getRepository(User::class);
        if (!(count($repository->findBy(['email' => $email]))))
        {
            $this->addFlash(
                'notice',
                'Данные email не существует!'
            );
            return $this->render('reset.html.twig');
        }
        $resetToken = new ResetToken();
        $token = md5(random_bytes(30));
        $encodedToken = md5($token);
        $resetToken->setEmail($email);

        $resetToken->setToken($encodedToken);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($resetToken);
        $entityManager->flush();
        $url = $this->generateUrl('update_password', ['email'=>$email, 'token'=>$token], UrlGeneratorInterface::ABSOLUTE_URL);
        $mail = (new Email())
        ->from('practiceiac@gmail.com')
        ->to($email)
        //->text('Sending emails is fun again!')
        ->subject('Восстановление пароля')
        ->html("<p>Ссылка: <a href='$url'>$url</a></p>");
        $mailer->send($mail);
        $this->addFlash('notice', 'На вашу почту была отправлена ссылка для восстановления пароля!');
        return $this->redirectToRoute('home');
    }
    /**
     * @Route("/updatepassword", name="update_password", methods={"POST", "GET"})
     */
    public function updatePassword(Request $request, UserPasswordEncoderInterface $passwordEncoder, LoggerInterface $logger)
    {
        $email = $request->get('email');
        $token = $request->get('token');
        $logger->debug($email);
        $logger->debug($token);
        if($request->isMethod('GET'))
        {
            return $this->render('updatepassword.html.twig', [
                'email' => $email, 'token' => $token
            ]);
        }

        if($request->isMethod('POST'))
        {
            $password = $request->get('password');

            $encodedToken = md5($token);
            $resetTokensRepository = $this->getDoctrine()->getRepository(ResetToken::class);

            $resetTokens = $resetTokensRepository->findBy(['email' => $email]);
            $resetToken = $resetTokensRepository->findOneBy(['email' => $email], ['expirationDate' => 'DESC']);

            $currentTime = new \DateTime();
            $logger->debug($resetToken->getToken());
            if ($resetToken->getToken() == $encodedToken && $currentTime < $resetToken->getExpirationDate()) 
            {
                $usersrepository = $this->getDoctrine()->getRepository(User::class);
                $user = $usersrepository->findOneBy(['email' => $email]);
                $passwordEncoded = $passwordEncoder->encodePassword($user, $password);
                $user->setPassword($passwordEncoded);
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                foreach ($resetTokens as $object) 
                {
                    $entityManager->remove($object);
                }
                $entityManager->flush();
            }
            $this->addFlash(
                'notice',
                'Ваш пароль успешно изменен!'
            );
            return $this->redirectToRoute('app_login');
        }
    }
    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }
}
