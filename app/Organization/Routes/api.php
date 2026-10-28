<?php

use App\Organization\Http\Controllers\OrganizationAuditLogController;
use App\Organization\Http\Controllers\OrganizationControlController;
use App\Organization\Http\Controllers\OrganizationRoleController;
use Illuminate\Support\Facades\Route;

/*
| Corporate organization routes. Loaded by OrganizationServiceProvider under /api/v1.
| Every route here passes tenant isolation (organization.tenant) and then a policy check in the controller.
| scopeBindings() makes child models (e.g. {roleAssignment}) resolve only inside {organization}.
*/

Route::middleware(['auth:sanctum', 'organization.user', 'organization.tenant'])
    ->prefix('organizations/{organization}')
    ->whereUuid('organization')
    ->scopeBindings()
    ->name('organizations.')
    ->group(function () {
        // Roles (ORG_OWNER / ORG_ADMIN / ORG_ANALYST / ORG_MEMBER)
        Route::get('roles', [OrganizationRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [OrganizationRoleController::class, 'store'])->name('roles.store');
        Route::delete('roles/{roleAssignment}', [OrganizationRoleController::class, 'destroy'])->name('roles.destroy');

        // Audit history
        Route::get('audit-logs', [OrganizationAuditLogController::class, 'index'])->name('audit-logs.index');

        // Pivot Super Admin control
        Route::post('suspend', [OrganizationControlController::class, 'suspend'])->name('suspend');
        Route::post('reactivate', [OrganizationControlController::class, 'reactivate'])->name('reactivate');
        Route::delete('/', [OrganizationControlController::class, 'destroy'])->name('destroy');
    });
