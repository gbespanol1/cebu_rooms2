<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Simple static authentication
        $users = [
            'admin' => 'password123',
            'staff' => 'password123',
            'faculty' => 'password123',
            'sysadmin' => 'password123',
            'ao' => 'password123',
            'adpd' => 'password123',
        ];

        if (isset($users[$credentials['username']]) &&
            $users[$credentials['username']] === $credentials['password']) {

            $request->session()->put('user', [
                'username' => $credentials['username'],
                'role' => $credentials['username']
            ]);

            return redirect('/MainDashboard');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
