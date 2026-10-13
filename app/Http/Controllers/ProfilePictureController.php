<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\ProtectedFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfilePictureController extends Controller
{
    /**
     * Display the authenticated user's profile picture.
     */
    public function show(Request $request): StreamedResponse
    {
        $user = $request->user();

        return $this->pictureResponse($user?->profile_picture_path);
    }

    /**
     * Display another user's profile picture.
     *
     * Staff working the SPMU workspace already see borrower names on the
     * oversight lists, so they may also load the matching avatar. Everyone
     * else is limited to their own picture.
     *
     * The workspace is resolved from the account's current access
     * classification on every request, never from a stored session value,
     * so a reclassified account cannot keep an older workspace's access.
     */
    public function showForUser(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();

        abort_unless($viewer, 401);

        abort_unless(
            $viewer->primaryWorkspace() === 'SPMU'
                || (int) $viewer->id === (int) $user->id,
            403
        );

        return $this->pictureResponse($user->profile_picture_path);
    }

    /**
     * Upload or replace the authenticated user's profile picture.
     */
    public function update(
        Request $request,
        AuditService $audit
    ): RedirectResponse {
        $data = $request->validate([
            'profile_picture' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $user = $request->user();

        $previousPath = $user->profile_picture_path;

        /*
         * Where the previous picture is must be settled before the new one
         * is written: it may sit on an older disk than the configured one.
         */
        $previousDisk = $this->pictureDisk($previousPath);

        try {
            $newPath = $data['profile_picture']->store(
                'profile-pictures/'.$user->id,
                $this->writeDisk()
            );
        } catch (Throwable $exception) {
            report($exception);
            $newPath = false;
        }

        /* A failed write must not be saved as the user's picture. */
        if (! is_string($newPath) || $newPath === '') {
            throw ValidationException::withMessages([
                'profile_picture' => 'The profile picture could not be saved. Please try again.',
            ]);
        }

        $user->forceFill([
            'profile_picture_path' => $newPath,
        ])->save();

        if ($previousPath && $previousPath !== $newPath) {
            $this->deletePicture($previousDisk, $previousPath);
        }

        $audit->record(
            'PROFILE_PICTURE_UPDATED',
            $user,
            before: [
                'profile_picture_path' => $previousPath,
            ],
            after: [
                'profile_picture_path' => $newPath,
            ],
        );

        return back()->with(
            'status',
            'Profile picture updated successfully.'
        );
    }

    /**
     * Remove the authenticated user's profile picture.
     */
    public function destroy(
        Request $request,
        AuditService $audit
    ): RedirectResponse {
        $user = $request->user();

        $previousPath = $user->profile_picture_path;

        if (! $previousPath) {
            return back()->with(
                'status',
                'No profile picture to remove.'
            );
        }

        $previousDisk = $this->pictureDisk($previousPath);

        $user->forceFill([
            'profile_picture_path' => null,
        ])->save();

        $this->deletePicture($previousDisk, $previousPath);

        $audit->record(
            'PROFILE_PICTURE_REMOVED',
            $user,
            before: [
                'profile_picture_path' => $previousPath,
            ],
            after: [
                'profile_picture_path' => null,
            ],
        );

        return back()->with(
            'status',
            'Profile picture removed successfully.'
        );
    }

    /** The disk new profile pictures are written to. */
    private function writeDisk(): string
    {
        return app(ProtectedFileService::class)->writeDisk();
    }

    /**
     * The disk a stored picture path is actually on, or null when it is on
     * none of them.
     *
     * users.profile_picture_path records a path and no disk. The configured
     * disk is checked first and the local disk second, so a picture saved
     * before the protected disk changed is still found where it was saved;
     * nothing assumes it was copied anywhere.
     */
    private function pictureDisk(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        foreach (array_unique([$this->writeDisk(), 'local']) as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return $disk;
                }
            } catch (Throwable $exception) {
                /* An unreachable disk is not proof the picture is missing. */
                report($exception);
            }
        }

        return null;
    }

    private function pictureResponse(?string $path): StreamedResponse
    {
        $disk = $this->pictureDisk($path);

        abort_unless($disk !== null, 404);

        return Storage::disk($disk)->response(
            $path,
            null,
            [
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Remove a replaced or removed picture from the disk it was found on.
     *
     * The profile has already been updated by this point, so a storage
     * failure here is reported and does not undo the user's change.
     */
    private function deletePicture(?string $disk, string $path): void
    {
        if ($disk === null) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
