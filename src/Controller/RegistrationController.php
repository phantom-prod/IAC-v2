<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RegisterToken;
use Symfony\Component\Mime\Email;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, MailerInterface $mailer): Response
    {
        $registerToken = new RegisterToken();
        $form = $this->createForm(RegistrationFormType::class, $registerToken);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $repository = $this->getDoctrine()->getRepository(User::class);
            if (count($repository->findBy(['email' => $email])))
            {
                $this->addFlash('notice', 'Данный email уже занят!');
                return $this->redirectToRoute('app_register');
            }
            $token = md5(random_bytes(30));
            $encodedToken = md5($token);
            $registerToken->setEmail($email);
            $passwordEncoded = $passwordEncoder->encodePassword($registerToken, $form->get('plainPassword')->getData());

            $registerToken->setPassword($passwordEncoded);

            $registerToken->setToken($encodedToken);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($registerToken);
            $entityManager->flush();
            $url = $this->generateUrl('check_register_token', ['email'=>$email, 'token'=>$token], UrlGeneratorInterface::ABSOLUTE_URL);
            $mail = (new Email())
            ->from('practiceiac@gmail.com')
            ->to($email)
            //->text('Sending emails is fun again!')
            ->subject('Подтверждение регистрации')
            ->html("<p>Ссылка: <a href='$url'>$url</a></p>");
            $mailer->send($mail);
            $this->addFlash('notice', 'На вашу почту была отправлена ссылка для продолжения регистрации!');
            return $this->redirectToRoute('home');
        }
        return $this->render('register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/confirm", name="check_register_token")
     */
    public function checkRegisterToken(Request $request)
    {
        $email = $request->query->get('email');
        $token = $request->query->get('token');
        $encodedToken = md5($token);
        $repository = $this->getDoctrine()->getRepository(RegisterToken::class);
        $registerTokens = $repository->findBy(['email' => $email]);
        $registerToken = $repository->findOneBy(['email' => $email], ['expirationDate' => 'DESC']);
        
        $currentTime = new \DateTime();

        if ($registerToken->getToken() == $encodedToken && $currentTime < $registerToken->getExpirationDate()) 
        {      
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($registerToken->getPassword());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            foreach ($registerTokens as $object) 
            {
                $entityManager->remove($object);
            }
            $entityManager->flush(); 
        }
        $this->addFlash(
            'notice',
            'Ваш аккаунт успешно создан!'
        );
        return $this->redirectToRoute('app_login');
    }
}
