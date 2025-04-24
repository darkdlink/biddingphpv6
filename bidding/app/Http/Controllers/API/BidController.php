<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BidController extends Controller
{
    public function index()
    {
        $bids = Bid::with('category')->get();
        return response()->json($bids);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'bid_number' => 'required|string|unique:bids',
            'bid_category_id' => 'required|exists:bid_categories,id',
            'opening_date' => 'required|date',
            'closing_date' => 'required|date|after:opening_date',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bid = Bid::create($request->all());
        return response()->json($bid, 201);
    }

    public function show($id)
    {
        $bid = Bid::with(['category', 'proposals', 'attachments'])->findOrFail($id);
        return response()->json($bid);
    }

    public function update(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'bid_number' => 'string|unique:bids,bid_number,' . $id,
            'bid_category_id' => 'exists:bid_categories,id',
            'opening_date' => 'date',
            'closing_date' => 'date|after:opening_date',
            'status' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bid->update($request->all());
        return response()->json($bid);
    }

    public function destroy($id)
    {
        $bid = Bid::findOrFail($id);
        $bid->delete();
        return response()->json(null, 204);
    }
}
