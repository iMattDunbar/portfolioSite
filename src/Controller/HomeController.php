<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class HomeController extends Controller
{
    /**
     * @Route("/", name="Home")
     */
    public function index()
    {
        //return $this->render('home/test.html.twig');
        return $this->render('home/index.html.twig');

        //return $this->redirect('http://www.mattdunbar.io');
    }


}
