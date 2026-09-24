<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyerRegistrationRequest;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\User;
use App\Services\SellerRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function showBuyerRegistrationForm() { return view('auth.register'); }

    public function register(BuyerRegistrationRequest $request)
    {
        $data = $request->validated();

        $validIdPath = $request->file('valid_id')->store('registration-documents');
        $user = DB::transaction(function () use ($data, $validIdPath) {
            $name = trim($data['first_name'].' '.(($data['middle_initial'] ?? null) ? $data['middle_initial'].'. ' : '').$data['last_name']);
            $user = User::create([
                'name' => $name, 'first_name'=>$data['first_name'], 'middle_initial'=>$data['middle_initial']??null,
                'last_name'=>$data['last_name'], 'sex'=>$data['sex'], 'birthday'=>$data['birthday'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'valid_id_path'=>$validIdPath, 'registration_type'=>'buyer', 'registration_status'=>'pending',
                'password' => Hash::make($data['password']),
                'is_active' => false,
            ]);
            $user->assignRole('buyer');
            $user->addresses()->create([
                'full_name' => $name, 'phone' => $data['phone'],
                'address_line' => $data['address_line'], 'region' => $data['region'] ?? null, 'region_code' => $data['region_code'] ?? null,
                'barangay' => $data['barangay'], 'barangay_code' => $data['barangay_code'] ?? null,
                'city' => $data['city'], 'city_code' => $data['city_code'] ?? null,
                'province' => $data['province'] ?? '', 'province_code' => $data['province_code'] ?? null,
                'postal_code' => $data['postal_code'], 'country' => $data['country'],
                'label' => 'Home', 'is_default' => true,
            ]);
            return $user;
        });

        event(new Registered($user));

        $this->notifyAdmins($user, 'Buyer');
        return redirect()->route('login')->with('success', 'Registration submitted. Your Buyer account is waiting for administrator approval. You will be notified after review.');
    }

    private function notifyAdmins(User $user, string $type): void
    {
        User::whereHas('roles', fn($query)=>$query->where('slug','admin'))->get()->each(fn($admin)=>\App\Services\NotificationService::send($admin->id,"New {$type} registration received.","{$user->name} is waiting for administrator approval.",'registration',route('admin.users.index', ['tab'=>'buyers']),['user_id'=>$user->id,'type'=>strtolower($type)],'user'));
    }
}
