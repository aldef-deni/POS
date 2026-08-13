<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PosAuthController extends Controller
{
    public function showLogin()
    {
        return view('pos.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if (!in_array($user->role, ['Kasir', 'Supervisor', 'Owner'])) {
                Auth::logout();
                return back()->withErrors(['email' => 'Unauthorized for POS']);
            }

            // Keep POS session separate from dashboard login
            session(['pos_user_id' => $user->id]);
            Auth::logout();
            return redirect()->route('pos.index');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout()
    {
        session()->forget('pos_user_id');
        return redirect()->route('pos.login');
    }
}
