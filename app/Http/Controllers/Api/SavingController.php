<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Savings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SavingController extends Controller
{
    /**
     * Display a listing of all savings.
     */
    public function index()
    {
        try {
            $savings = Savings::all();

            return response()->json([
                'status' => 'success',
                'message' => 'List of all savings',
                'data' => $savings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch savings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get savings by status (tercapai/berlangsung).
     */
    public function getSavingsByStatus($status)
    {
        try {
            if (!in_array($status, ['tercapai', 'berlangsung'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Status tidak valid. Gunakan "tercapai" atau "berlangsung".',
                ], 400);
            }

            $user = Auth::user();

            $savings = Savings::where('status', $status)
                ->where('user_id', $user->id)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => "List of savings with status: $status",
                'data' => $savings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch savings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created saving.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:3',
                'target_amount' => 'required|integer|min:1',
                'saving_frequency' => 'required|in:harian,mingguan,bulanan',
                'nominal_per_frequency' => 'required|integer|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'image' => 'nullable|image|mimes:jpg,jpeg,png',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $request->merge(['user_id' => $user->id]);

            $filename = null;
            if ($request->hasFile('image')) {
                if (!$request->file('image')->isValid()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid file upload',
                    ], 400);
                }

                $user = $request->user();
                $firstName = explode(' ', $user->name)[0];

                $filename = strtolower($firstName) . '-' . time() . '.' . $request->image->extension();

                $path = $request->file('image')->storeAs('savings', $filename);

                if (!$path) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to save file',
                    ], 500);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No file uploaded',
                ], 400);
            }

            $saving = Savings::create([
                'user_id' => $request->user_id,
                'name' => $request->name,
                'target_amount' => (int) $request->target_amount,
                'saving_frequency' => $request->saving_frequency,
                'nominal_per_frequency' => (int) $request->nominal_per_frequency,
                'current_savings' => 0,
                'remaining_amount' => (int) $request->target_amount,
                'remaining_days' => (int) $this->calculateRemainingDays($request->start_date, $request->end_date),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 'berlangsung',
                'image' => $filename,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Saving successfully created',
                'data' => $saving,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create saving: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show specific saving data. (Belum Optimal)
     */
    public function show($id)
    {
        $saving = Savings::with('user')->find($id);

        if (!$saving) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tabungan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tabungan',
            'data' => $saving,
        ]);
    }

    /**
     * Remove the specified saving. (Belum Optimal)
     */
    public function destroy($id)
    {
        $saving = Savings::find($id);

        if (!$saving) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tabungan tidak ditemukan',
            ], 404);
        }

        $saving->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tabungan berhasil dihapus',
        ]);
    }

    /**
     * Add savings to a specific saving entry.
     */
    public function addSaving(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $user = Auth::user();

            $saving = Savings::find($id);

            if (!$saving) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Saving not found',
                ], 404);
            }

            if ($saving->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to update this saving',
                ], 403);
            }

            $saving->current_savings += $request->amount;
            $saving->remaining_amount = max(0, $saving->target_amount - $saving->current_savings);

            if ($saving->current_savings >= $saving->target_amount) {
                $saving->status = 'tercapai';
                $saving->remaining_days = 0;
            }

            $saving->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Saving balance updated successfully',
                'data' => $saving,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update saving: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Utility function to calculate remaining days.
     */
    private function calculateRemainingDays($start_date, $end_date)
    {
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        return $start->diff($end)->days;
    }
}
