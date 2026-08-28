<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class SettingsController extends Controller {
 public function index(Request $request){ $setting=$request->user()->store->settings()->firstOrCreate(); return view('seller.settings.index',compact('setting')); }
 public function account(Request $request){ $data=$request->validate(['name'=>['required','string','max:255'],'phone'=>['nullable','string','max:30']]); $request->user()->update($data); return back()->with('success','Account updated.'); }
 public function shipping(Request $request){ $data=$request->validate(['shipping_fee'=>['required','numeric','min:0'],'processing_days'=>['required','integer','between:1,30'],'cod_enabled'=>['nullable','boolean']]); $data['cod_enabled']=$request->boolean('cod_enabled'); $request->user()->store->settings()->updateOrCreate([], $data); return back()->with('success','Shipping settings updated.'); }
 public function password(Request $request){ $data=$request->validate(['current_password'=>['required','current_password'],'password'=>['required','confirmed','min:8']]); $request->user()->update(['password'=>Hash::make($data['password'])]); return back()->with('success','Password changed successfully.'); }
}
