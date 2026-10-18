<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Property;
use App\Services\Announcement\AnnouncementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnnouncementWebController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'target_role', 'search']);

        $announcements = $this->announcementService
            ->getAnnouncementsQuery($user, $filters)
            ->paginate(12)
            ->withQueryString();

        // Ambil daftar properti yang dapat diakses pengguna untuk filter & modal
        $propertiesQuery = Property::query();
        if (!$user->isSuperAdmin()) {
            $accessiblePropertyIds = DB::table('property_users')
                ->where('user_id', $user->id)
                ->pluck('property_id');
            $propertiesQuery->whereIn('id', $accessiblePropertyIds);
        }
        $properties = $propertiesQuery->orderBy('name')->get();

        $unreadCount = $this->announcementService->getUnreadCount($user);

        return view('dashboard.announcements.index', [
            'announcements' => $announcements,
            'properties' => $properties,
            'filters' => $filters,
            'unreadCount' => $unreadCount,
            'canManage' => !$user->isTenant(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_role' => 'nullable|in:all,tenant,staff',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        try {
            $this->announcementService->createAnnouncement(
                $request->all(),
                $user,
                $request->file('attachment')
            );

            return redirect()->route('dashboard.announcements.index')
                ->with('success', 'Pengumuman baru berhasil diterbitkan.');
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_role' => 'nullable|in:all,tenant,staff',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        try {
            $this->announcementService->updateAnnouncement(
                $id,
                $request->all(),
                $user,
                $request->file('attachment')
            );

            return redirect()->route('dashboard.announcements.index')
                ->with('success', 'Pengumuman berhasil diperbarui.');
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->announcementService->deleteAnnouncement($id, $user);

            return redirect()->route('dashboard.announcements.index')
                ->with('success', 'Pengumuman berhasil dihapus.');
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
