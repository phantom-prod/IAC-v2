<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Book;
use App\Entity\Genre;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;


class MainController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function renderBooks()
    {
        $repository = $this->getDoctrine()->getRepository(Book::class);
        $books = $repository->findAll();

        return $this->render('main.html.twig', [
            'books'=> $books
        ]);
    }
    /**
     * @Route("/search", name="search")
     */
    public function findBooks(Request $request)
    {
        $search = $request->query->get('search');
        $repository = $this->getDoctrine()->getRepository(Book::class);
        $books = $repository->findBooks($search);
        return $this->render('main.html.twig', [
            'books'=> $books
        ]);
    }
    /**
     * @Route("/genres", name="genres")
     */
    public function renderGenres(SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository(Genre::class);
        $genres = $repository->findAll();
        $response = new Response();
        $encoded_data = $serializer->serialize($genres, 'json', [AbstractNormalizer::IGNORED_ATTRIBUTES => ['book', 'discription']]);
        $response->setContent($encoded_data);
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }
    /**
     * @Route("/genres/{id<\d>}", name="genresid")
     */
    public function genresId($id)
    {
        $repository = $this->getDoctrine()->getRepository(Genre::class);
        $genre = $repository->find($id);
        $books = $genre->getBook();

        return $this->render('main.html.twig', [
            'books'=> $books
        ]);
    }
}
