<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RatingDimensionController extends Controller
{
    public function index()
    {
        return \App\Models\RatingDimension::orderBy('name')
            ->get();
    }
}
