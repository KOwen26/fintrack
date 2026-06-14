<?php

namespace App\Http\Controllers;

use App\Data\DataTablePayloadData;
use App\Data\UserTestData;
use App\Helpers\DataTableHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('home', [
            'user' => UserTestData::from([
                'name' => 'John Doe',
                'age' => 25,
                'is_married' => false,
                'hobbies' => ['Reading', 'Coding', 'Traveling'],
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'zip' => '10001',
                ],
            ]),
        ]);
    }

    public function table()
    {
        return Inertia::render('dev/table', [
            'users' => User::all(),
        ]);
    }

    public function tableServer(Request $request)
    {
        $payload = DataTablePayloadData::fromQueryParams($request->query());

        $query = User::query();

        $result = DataTableHelper::parse($query, $payload);

        return response()->json($result);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
