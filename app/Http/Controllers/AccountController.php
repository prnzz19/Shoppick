<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function profile()
    {
        $user = auth()->user();
        $orders = $user->orders()->with('items')->latest()->take(5)->get();
        return view('storefront.account.profile', compact('user', 'orders'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']]);
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function changePasswordForm()
    {
        return view('storefront.account.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->input('current_password'), auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('success', 'Password changed successfully.');
    }

}
