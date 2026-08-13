<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class PosController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!session('pos_user_id')) {
                return redirect()->route('pos.login');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $products = Product::orderBy('name')->get();
        return view('pos.pos', compact('products'));
    }

    public function printReceipt(Request $request)
    {
        $data = $request->all();
        return view('pos.print', compact('data'));
    }
}
