<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Services\Messages\MessagesServiceInterface;

class MessagesController extends Controller
{
    protected $messagesService;
    public function __construct(MessagesServiceInterface $messagesService)
    {
        $this->messagesService = $messagesService;
    }
    public function create(Request $request)
    {

    }
}
