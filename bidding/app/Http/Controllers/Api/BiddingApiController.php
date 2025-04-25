<?php
// app/Http/Controllers/Api/BiddingApiController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bidding;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BiddingApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Bidding::with('company');

        // Filtragem
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('modality')) {
            $query->where('modality', $request->modality);
        }

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('bidding_number', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        // Ordenação
        $orderBy = $request->input('order_by', 'opening_date');
        $orderDirection = $request->input('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->input('per_page', 15);
        $biddings = $query->paginate($perPage);

        return response()->json($biddings);
    }

    public function show($id)
    {
        $bidding = Bidding::with(['company', 'proposals'])->findOrFail($id);
        return response()->json($bidding);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'bidding_number' => 'required|unique:biddings',
            'company_id' => 'required|exists:companies,id',
            'modality' => 'required',
            'opening_date' => 'required|date',
            'estimated_value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $bidding = Bidding::create($request->all());

        return response()->json($bidding, 201);
    }

    public function update(Request $request, $id)
    {
        $bidding = Bidding::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'bidding_number' => 'required|unique:biddings,bidding_number,' . $id,
            'company_id' => 'required|exists:companies,id',
            'modality' => 'required',
            'opening_date' => 'required|date',
            'estimated_value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $bidding->update($request->all());

        return response()->json($bidding);
    }

    public function destroy($id)
    {
        $bidding = Bidding::findOrFail($id);
        $bidding->delete();

        return response()->json(null, 204);
    }

    public function proposals($id)
    {
        $bidding = Bidding::findOrFail($id);
        $proposals = $bidding->proposals;

        return response()->json($proposals);
    }

    public function storeProposal(Request $request, $id)
    {
        $bidding = Bidding::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'total_cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,submitted,won,lost',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $proposal = new Proposal($request->all());
        $bidding->proposals()->save($proposal);

        return response()->json($proposal, 201);
    }
}
