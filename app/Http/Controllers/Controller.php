<?php

namespace App\Http\Controllers;

use App\Services\VeranstaltungenService;

class Controller
{
    public function index()
    {
        $veranstaltungenService = new VeranstaltungenService();

        return $veranstaltungenService->all();
    }
}
