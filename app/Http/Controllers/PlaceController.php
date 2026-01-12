<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index()
    {
        $places = Place::all();

        return view('places.index', compact('places'));
    }

    public function create()
    {
        return view('places.create');
    }

    public function store(Request $request)
    {
        // Validate the request data as per your requirements
        $validatedData = $request->validate([
            'name' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        Place::create($validatedData);

        return redirect()->route('places.index')->with('success', 'Location created successfully');
    }

    public function show(Place $place)
    {
        return view('places.show', compact('place'));
    }
}
