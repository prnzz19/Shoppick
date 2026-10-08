<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AdminActivityLog, SellerApplication, SellerProfile, Store, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};

class SellerArchiveController extends Controller
{
    public function update(Request $request, int $record, string $entity, string $action)
    {
        abort_unless($request->user()?->isAdmin() && $request->user()->hasPermissionTo('manage_sellers'), 403);
        [$model, $route, $label] = match ($entity) {
            'shops' => [Store::class, 'admin.shops.index', 'Shop'],
            'applications' => [SellerApplication::class, 'admin.sellers.applications.index', 'Seller application'],
            'sellers' => [SellerProfile::class, 'admin.sellers.index', 'Seller'],
            default => abort(404),
        };
        try {
            return DB::transaction(function () use ($model, $record, $action, $route, $label) {
                $target = $model::findOrFail($record);
                // Match approval's lock order so archiving cannot race an approval.
                User::whereKey($target->user_id)->lockForUpdate()->firstOrFail();
                $target = $model::whereKey($record)->lockForUpdate()->firstOrFail();
                if ($action === 'delete') {
                    abort_unless($target->archived_at, 422, 'Only archived records can be permanently deleted.');
                    if ($this->hasHistory($target)) {
                        return back()->with('error', 'This record cannot be permanently deleted because related marketplace history exists. Keep it archived instead.');
                    }
                    AdminActivityLog::record('seller_management.deleted', $model, $record);
                    $target instanceof Store ? $target->forceDelete() : $target->delete();

                    return redirect()->route($route, ['tab' => 'archived'])->with('success', "$label permanently deleted successfully.");
                }
                abort_unless(in_array($action, ['archive', 'restore'], true), 404);
                if (($action === 'archive') === (bool) $target->archived_at) {
                    return back()->with('error', $action === 'archive' ? 'This record is already archived.' : 'This record is not archived.');
                }
                $target->forceFill(['archived_at' => $action === 'archive' ? now() : null])->save();
                AdminActivityLog::record('seller_management.'.$action, $model, $record);

                return back()->with('success', "$label ".($action === 'archive' ? 'archived' : 'restored').' successfully.');
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The record could not be updated. Please try again.');
        }
    }

    private function hasHistory($target): bool
    {
        // All incoming foreign keys are protected, including nullable audit references.
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $key) {
                if ($key['foreign_table'] !== $target->getTable()) continue;
                foreach ($key['columns'] as $index => $column) {
                    if (($key['foreign_columns'][$index] ?? null) === 'id'
                        && DB::table($table['name'])->where($column, $target->id)->exists()) return true;
                }
            }
        }
        if (DB::table('reports')->where('target_type', $target->getMorphClass())->where('target_id', $target->id)->exists()) return true;
        if ($target instanceof SellerApplication) {
            return $target->status === 'approved' || $target->user->sellerProfile()->exists()
                || $target->user->store()->withTrashed()->exists();
        }
        // A profile/shop linked to an application is part of the approval history.
        return SellerApplication::where('user_id', $target->user_id)->exists();
    }
}
