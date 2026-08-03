<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /** Permite llamar a $this->authorize() desde los controladores. */
    use AuthorizesRequests;
}
