<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Http\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home', [
            'title' => 'Home',
        ]);
    }
}
