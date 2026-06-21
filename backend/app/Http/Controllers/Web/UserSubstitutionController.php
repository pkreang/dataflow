<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSubstitution;
use App\Notifications\SubstitutionAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserSubstitutionController extends Controller
{
    public function index(): View
    {
        $substitutions = UserSubstitution::query()
            ->with(['fromUser', 'toUser', 'createdBy'])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('settings.substitutions.index', compact('substitutions'));
    }

    public function create(): View
    {
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('settings.substitutions.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id|different:from_user_id',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'reason' => 'nullable|string|max:500',
        ]);

        $substitution = UserSubstitution::create([
            ...$validated,
            'is_active' => true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $substitute = User::find($substitution->to_user_id);
        $substitute?->notify(new SubstitutionAssigned($substitution->load('fromUser')));

        return redirect()->route('settings.substitutions.index')
            ->with('success', __('common.substitution_created'));
    }

    public function destroy(UserSubstitution $substitution): RedirectResponse
    {
        $substitution->delete();

        return redirect()->route('settings.substitutions.index')
            ->with('success', __('common.deleted'));
    }

    public function toggleActive(UserSubstitution $substitution): RedirectResponse
    {
        $substitution->update(['is_active' => ! $substitution->is_active]);

        return redirect()->route('settings.substitutions.index')
            ->with('success', __('common.saved'));
    }
}
