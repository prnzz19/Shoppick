<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

class BuyerIdentity
{
    public static function phone(string $value): string
    {
        $value=trim($value);
        return preg_match('/^09[0-9]{9}$/D',$value)?'+63'.substr($value,1):$value;
    }
    public static function nameRules(): array {return ['required','string','min:2','max:50',"regex:/^[\\p{L} .'-]+$/u",'regex:/\\p{L}/u'];}
    public static function passwordRules(): array {return ['required','confirmed',Password::min(8)->mixedCase()->numbers()->symbols(),'regex:/[^\\p{L}\\p{N}\\s]/u'];}
    public static function birthdayCutoff(): string {return today('Asia/Manila')->subYears(18)->format('Y-m-d');}
    public static function emailRule(?int $except=null): \Closure
    {
        return function($attribute,$value,$fail)use($except){
            if(User::withTrashed()->when($except,fn($q)=>$q->whereKeyNot($except))->whereRaw('LOWER(TRIM(email)) = ?',[mb_strtolower(trim($value))])->exists())$fail('That email is already registered.');
        };
    }
    public static function phoneRule(?int $except=null): \Closure
    {
        return function($attribute,$value,$fail)use($except){
            $canonical=self::phone($value);$local='0'.substr($canonical,3);$bare=ltrim($canonical,'+');
            if(User::withTrashed()->when($except,fn($q)=>$q->whereKeyNot($except))->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') IN (?, ?, ?)",[$canonical,$local,$bare])->exists())$fail('That mobile number is already registered.');
        };
    }
    public static function needsVerification(User $user): bool {return $user->registration_type==='buyer'&&$user->registration_status==='unverified';}
}
