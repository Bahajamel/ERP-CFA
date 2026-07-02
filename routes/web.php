<?php

use Illuminate\Support\Facades\Route;

// La racine renvoie directement vers le panneau d'administration (l'application).
Route::redirect('/', '/admin');
