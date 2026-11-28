<?php

namespace App\Http\Controllers;

use App\Models\UserRoleAudit;
use Illuminate\View\View;

class UserRoleAuditController extends Controller
{
    /**
     * Read-only audit history. The route is restricted to Admin role.
     */
    public function index(): View
    {
        $audits = UserRoleAudit::query()
            ->latest('id')
            ->paginate(30);

        return view('admin.audit-role.index', compact('audits'));
    }
}
