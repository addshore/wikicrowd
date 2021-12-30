<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuDepicts;

class QuDepictsController extends Controller
{

    public function show()
    {
        return view('edit', [
            'qu' => QuDepicts::first()
        ]);
    }

}
