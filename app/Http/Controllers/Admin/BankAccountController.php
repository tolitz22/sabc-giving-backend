<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        return BankAccountResource::collection(
            BankAccount::with('creator:id,name,email')
                ->orderBy('sort_order')
                ->orderBy('bank_name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);

        $data = $request->validate($this->rules());

        $account = BankAccount::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'bank_account' => new BankAccountResource($account->load('creator:id,name,email')),
        ], 201);
    }

    public function update(Request $request, BankAccount $bankAccount): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);

        $bankAccount->update($request->validate($this->rules()));

        return response()->json([
            'bank_account' => new BankAccountResource($bankAccount->load('creator:id,name,email')),
        ]);
    }

    public function enable(Request $request, BankAccount $bankAccount): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);

        $bankAccount->forceFill(['is_enabled' => true])->save();

        return response()->json([
            'bank_account' => new BankAccountResource($bankAccount->load('creator:id,name,email')),
        ]);
    }

    public function disable(Request $request, BankAccount $bankAccount): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);

        $bankAccount->forceFill(['is_enabled' => false])->save();

        return response()->json([
            'bank_account' => new BankAccountResource($bankAccount->load('creator:id,name,email')),
        ]);
    }

    private function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_enabled' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
