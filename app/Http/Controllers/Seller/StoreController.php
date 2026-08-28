<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function edit(Request $request) { return view('seller.store.edit', ['store' => $request->user()->store]); }

    public function update(Request $request)
    {
        $store = $request->user()->store;
        $data = $request->validate(['name'=>['required','string','max:120'],'description'=>['nullable','string','max:2000'],
            'location'=>['nullable','string','max:255'],'logo'=>['nullable','image','mimes:jpeg,png,jpg,webp','max:2048'],
            'banner'=>['nullable','image','mimes:jpeg,png,jpg,webp','max:4096']]);
        foreach (['logo','banner'] as $field) if ($request->hasFile($field)) { if ($store->{$field}) Storage::disk('public')->delete($store->{$field}); $data[$field]=$request->file($field)->store('stores','public'); }
        $store->update($data); AdminActivityLog::record('store.updated','store',$store->id,['name'=>$store->name]);
        return back()->with('success','Store profile updated.');
    }
}
