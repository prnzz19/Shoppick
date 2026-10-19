<?php

namespace App\Services;

use App\Models\{ProofOfDelivery, Shipment, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RiderProofOfDeliveryService
{
    public function submit(Request $request, Shipment $bound, User $rider, ?string $version = null): ProofOfDelivery
    {
        $path = null;
        try {
            $pod = app(RiderPortalActionService::class)->locked($bound, $rider, $version, function (Shipment $shipment, User $user) use ($request, &$path): ProofOfDelivery {
                if ($shipment->rider_id !== $user->id) abort(403);
                if ($shipment->status !== 'out_for_delivery') app(RiderPortalActionService::class)->stale();
                $existing = $shipment->proofOfDelivery;
                $data = $request->validate([
                    'version' => ['nullable', 'string', 'size:64'],
                    'recipient_name' => ['required', 'string', 'max:100'],
                    'photo' => [$existing ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                    'notes' => ['nullable', 'string', 'max:1000'],
                ]);
                $photo = $request->file('photo');
                $samePhoto = ! $photo || ($existing?->photo_path && Storage::disk('local')->exists($existing->photo_path)
                    && hash_file('sha256', $photo->getRealPath()) === hash_file('sha256', Storage::disk('local')->path($existing->photo_path)));
                if ($existing && $samePhoto && $existing->recipient_name === $data['recipient_name'] && $existing->notes === ($data['notes'] ?? null)) return $existing;
                if ($photo) $path = $photo->store('pod', 'local');
                $result = ProofOfDelivery::updateOrCreate(['shipment_id' => $shipment->id], [
                    'submitted_by' => $user->id, 'recipient_name' => $data['recipient_name'], 'photo_path' => $path ?? $existing?->photo_path,
                    'notes' => $data['notes'] ?? null, 'status' => 'pending', 'submitted_at' => now(), 'reviewed_by' => null, 'review_notes' => null, 'reviewed_at' => null,
                ]);
                foreach (User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'logistics'))->get() as $manager) {
                    NotificationService::send($manager->id, 'Proof of Delivery submitted.', "{$shipment->shipment_number} requires POD review.", 'logistics', route('logistics.pod', ['view' => $result->id]), ['pod_id' => $result->id], 'package');
                }
                return $result;
            });
            return $pod;
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
    }
}
