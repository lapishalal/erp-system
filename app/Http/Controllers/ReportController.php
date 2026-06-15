<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Report index']);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Report show', 'id' => $id]);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Report store']);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Report update']);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Report destroy']);
    }
}